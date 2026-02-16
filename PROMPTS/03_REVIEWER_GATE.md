# Task: Implement Reviewer Gate for AI-generated Questions

## Goal
Before saving AI-generated questions as usable drafts, run a reviewer pass:
- Detect ambiguity, multiple-correct risk, weak distractors, off-topic, language issues
- Output JSON validates `/schemas/reviewer.schema.json`
- If fail -> mark question_version as needs_review with issues

## Deliverables
- app/Services/AI/Prompts/ReviewerPrompt.php
- app/Domain/Questions/Actions/ReviewGeneratedQuestion.php
- fields on question_versions: reviewer_status, reviewer_issues jsonb
- Tests:
  - fail blocks promotion
  - pass allows remaining as draft

## Acceptance criteria
1) reviewer JSON validated
2) fail reasons stored and visible in Filament
