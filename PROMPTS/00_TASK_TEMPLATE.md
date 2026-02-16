# Task Template (for Codex)

## Goal
<one sentence: what should exist when done?>

## Context
- Module: <Question Bank / Exams / Attempts / Grading / Appeals / Analytics>
- Relevant paths: <app/Domain/... app/Filament/... database/migrations/...>
- Current assumptions: <what already exists?>

## Deliverables
- Code changes:
  - <files or components>
- Database:
  - <migrations + seeders>
- Tests:
  - <which tests, what they assert>

## Out of scope
- <explicitly exclude likely scope creep>

## Acceptance criteria
1) ...
2) ...
3) ...

## Verification
Run and summarize:
- php artisan test
- php artisan migrate:fresh --seed (if applicable)

## Notes
- Follow AGENTS.md rules.
- All AI JSON must validate against schemas in /schemas.
