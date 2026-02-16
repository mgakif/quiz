# Task: Implement AI Grading for Open-ended Items (rubric-based)

## Goal
Grade Short Answer / Essay attempt items using rubric criteria:
- produce criterion-level scores + evidence quotes + total + confidence + flags
- validate JSON against `/schemas/openended_grade.schema.json`
- store results in `ai_gradings` and `rubric_scores` (draft), requiring teacher approval if confidence low

## Deliverables
- app/Services/AI/Prompts/OpenEndedGradePrompt.php
- app/Domain/Grading/Jobs/AIGradeAttemptItemJob.php
- tables:
  - ai_gradings (attempt_item_id, response_json, confidence, flags, status)
- Filament:
  - Grading Queue page with filters: confidence<0.7, flagged, needs_review
  - Approve/Override action (requires override_reason)
- Tests:
  - grading pipeline persists draft score
  - low confidence routes to teacher queue
  - override requires reason and is audited

## Acceptance criteria
1) AI output validates schema; otherwise needs_review.
2) confidence + flags always stored.
3) Teacher override produces audit record.

## Verification
- php artisan test
