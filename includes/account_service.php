<?php
declare(strict_types=1);

/** Administrator-only account management. Every identity change revokes old sessions. */
function manage_account(array $input): int
{
    require_admin();
    if (!student_schema_ready()) {
        throw new DomainException('Apply the Student portal database migration before managing accounts.');
    }

    return transaction(function () use ($input): int {
        $action = request_string($input, 'action');
        $id = (int) ($input['id'] ?? 0);
        rows("SELECT id FROM users WHERE role='Administrator' AND is_active=1 AND registration_status='Approved' FOR UPDATE");
        $before = $id ? row('SELECT * FROM users WHERE id=? FOR UPDATE', [$id]) : null;
        if ($action !== 'create' && !$before) {
            throw new DomainException('Account not found.');
        }
        if ($id === (int) current_user()['id'] && $action !== 'create') {
            throw new DomainException('Use your profile for personal changes. Another administrator must change your role or access.');
        }

        if ($action === 'create') {
            $name = text_value($input, 'full_name', 150);
            $username = text_value($input, 'username', 80);
            $role = account_role(request_string($input, 'role'));
            $password = account_password($input);
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,80}$/', $username)) {
                throw new DomainException('Username must contain 3–80 letters, numbers, dots, underscores, or hyphens.');
            }
            $studentId = $role === 'Student' ? account_student_link($input) : null;
            query('INSERT INTO users(full_name,username,password_hash,role,student_id,must_change_password) VALUES(?,?,?,?,?,1)', [
                $name, $username, password_hash($password, PASSWORD_BCRYPT), $role, $studentId,
            ]);
            $id = (int) db()->lastInsertId();
        } elseif ($action === 'reset') {
            query('UPDATE users SET password_hash=?,must_change_password=1,auth_version=auth_version+1 WHERE id=?', [
                password_hash(account_password($input), PASSWORD_BCRYPT), $id,
            ]);
        } elseif ($action === 'approve') {
            if ($before['role'] === 'Student' && !$before['student_id']) {
                throw new DomainException('Verify and link this Student account to an academic record before approval.');
            }
            query("UPDATE users SET registration_status='Approved',is_active=1,auth_version=auth_version+1 WHERE id=?", [$id]);
            notify($id, 'Your account registration has been approved. You can now sign in.');
        } elseif ($action === 'reject') {
            query("UPDATE users SET registration_status='Rejected',is_active=0,auth_version=auth_version+1 WHERE id=?", [$id]);
        } elseif ($action === 'toggle') {
            query('UPDATE users SET is_active=1-is_active,auth_version=auth_version+1 WHERE id=?', [$id]);
        } elseif ($action === 'role') {
            $role = account_role(request_string($input, 'role'));
            if ($role !== 'Faculty' && scalar('SELECT COUNT(*) FROM offerings WHERE faculty_id=?', [$id])) {
                throw new DomainException('Reassign this faculty member’s classes before changing their role.');
            }
            $studentId = $role === 'Student' ? account_student_link($input) : null;
            query('UPDATE users SET role=?,student_id=?,auth_version=auth_version+1 WHERE id=?', [$role, $studentId, $id]);
        } elseif ($action === 'link') {
            if ($before['role'] !== 'Student') {
                throw new DomainException('Only a Student account can be linked to a student academic record.');
            }
            $studentId = account_student_link($input);
            query('UPDATE users SET student_id=?,auth_version=auth_version+1 WHERE id=?', [$studentId, $id]);
            notify($id, $studentId ? 'Your academic-record link was verified by an administrator. Please sign in again.' : 'Your academic-record link was removed. Contact the administrator for assistance.');
        } else {
            throw new DomainException('Unknown account action.');
        }

        $after = row('SELECT id,full_name,username,email,role,student_id,is_active,registration_status,must_change_password FROM users WHERE id=?', [$id]);
        audit('user_' . $action, 'users', $id, 'Account ' . $action, $before, $after);
        return $id;
    });
}

function account_role(string $role): string
{
    if (!in_array($role, ['Administrator', 'Faculty', 'Student'], true)) {
        throw new DomainException('Choose a valid account role.');
    }
    return $role;
}

function account_password(array $input): string
{
    $password = request_string($input, 'password');
    if (strlen($password) < 10 || strlen($password) > 72) {
        throw new DomainException('Use a password of 10–72 bytes.');
    }
    return $password;
}

function account_student_link(array $input): ?int
{
    $value = request_string($input, 'student_id');
    if ($value === '') {
        return null;
    }
    $id = positive_id($value);
    if (!row('SELECT id FROM students WHERE id=? FOR UPDATE', [$id])) {
        throw new DomainException('Choose an existing student academic record.');
    }
    return $id;
}
