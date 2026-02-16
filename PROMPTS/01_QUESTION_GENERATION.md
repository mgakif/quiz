# Task: Implement AI Question Generation (draft -> bank)

## Goal
Given a Blueprint (topic/kazanım, type counts, difficulty distribution), generate draft questions as JSON, validate with schema, and store as Question + QuestionVersion in the bank (status=draft).

## Context
- Laravel 12, Filament 5.
- Questions are versioned (Question + QuestionVersion).
- AI outputs must validate `/schemas/question_generate.schema.json`.

## Deliverables
- app/Services/AI/AIClient.php (or equivalent)
- app/Services/AI/Prompts/QuestionGeneratePrompt.php
- app/Services/AI/SchemaValidator.php (JSON schema validate)
- app/Domain/Questions/Actions/GenerateQuestionsFromBlueprint.php
- Migration/table for `ai_generations` (store request_hash, response_json, model, tokens, status)
- Tests:
  - schema validation passes for a fixture JSON
  - invalid JSON is rejected and marked needs_review
  - generated questions are persisted as drafts

## Out of scope
- No automatic publishing to exams
- No grading yet

## Acceptance criteria
1) Generated JSON validates against schema and persists as draft questions.
2) If schema invalid: one retry with “fix JSON”; still invalid -> needs_review.
3) No PII included in AI payload.

## Verification
- php artisan test
- php artisan migrate:fresh --seed
