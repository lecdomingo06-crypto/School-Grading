# ISU grading and application decisions

Source: [Isabela State University Student Manual](https://isu.edu.ph/wp-content/uploads/2025/10/Student-Manual.pdf), Section 18, printed page 24, continuation on page 25. Verified November 6, 2026. This application is not an official university registration system or a certification of a student's academic standing.

| Integer percentage | Numerical grade |
|---|---|
| 98–100 | 1.00 |
| 95–97 | 1.25 |
| 92–94 | 1.50 |
| 89–91 | 1.75 |
| 86–88 | 2.00 |
| 83–85 | 2.25 |
| 80–82 | 2.50 |
| 77–79 | 2.75 |
| 75–76 | 3.00 |
| 74 and below | 5.00 |
| Requirements incomplete with passing standing | INC |

The application computes `round((midterm × midterm_weight + final × (100 − midterm_weight)) / 100, 2)`. Marks are validated from 0 to 100 with two decimal places. The integer conversion then uses **floor** or **nearest whole number, half up**, according to the saved policy version. Under floor, 74.99 fails; under nearest, 74.50 converts to 75. The initial 50/50 weights and floor conversion are **application defaults, not verified ISU rules**. They remain visibly unapproved until an administrator records actual institutional approval. Submission is blocked for unapproved policies.

Grade records save their policy version, units, and academic/nonacademic designation. Later changes to subject units or the dashboard interpretation do not change saved grade conversions. The preview uses the selected policy; the server calculation is authoritative. Legacy records require explicit review and have before/after history.

GWA is `sum(numeric grade × saved units) / sum(saved units)` for eligible published, reviewed records. INC is excluded. Default interpretation includes all numeric attempts and excludes nonacademic subjects. Administrators can create policies using all attempts, latest chronological attempt, or best numerical attempt and can choose whether to include nonacademic subjects. Settings selects the default interpretation; reports identify the selected version. A report's filters define its population before repeated-attempt selection. A selected term therefore considers attempts within that term, not an unseen transcript-wide replacement rule.

These GWA inclusion rules, repeat rules, decimal handling, and assessment weights require confirmation by the relevant campus/program. Do not use the dashboard to award honors or make graduation decisions; those have additional institutional requirements not modeled here.

INC records preserve passing standing marks, unmet requirements, issue date, and a deadline copied from the term's approved academic calendar. This is an explicit calendar date, not a fixed 365-day approximation. Authorized completion requires an active student, current enrollment in the university, no recorded honorable dismissal, and a registrar completion-form/receipt reference. A published INC must be reopened before completion, followed by approval and publication. Original INC history remains available to authorized staff; a completed academic report shows the resulting numeric mark.

Expired pending INC records become 5.00 with an audit trail and notification. Run `php bin/console.php expire-inc` daily; the administrator dashboard also checks for expirations. Deadline edits never silently change existing INC snapshots. The school must configure the academic-year deadlines correctly and perform its actual registrar/enrollment/fee verification; storing a reference does not itself process a payment or submit a university form.

At-risk signals are transparent: active students with a published failed grade in the selected active term. Historical failures remain searchable by term and status. Intervention tracking records follow-up actions; it does not predict risk using AI.
