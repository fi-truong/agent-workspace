# AGENTS.md — AI+ / Agent Workspace

## Purpose

This file is the shared working guide for coding agents in AI+.
Read it before making changes.

## Start of a task

1. Read this file, then [`CLAUDE.md`](CLAUDE.md).
2. Check the working tree before editing. Preserve unrelated user changes.
3. Read the relevant product and technical documentation before changing behavior:
   - `PROJECT_SPEC.md` and `PROJECT_SPEC_EN.md` — product requirements and known gaps
   - `docs/openai-integration.md` — OpenAI integration
   - `docs/DEPLOYMENT_CHECKLIST_VI.md` — deployment requirements
4. If `.ai/rules/` exists, read its index and the rules relevant to the files in scope.

## Non-negotiable boundaries

- Do not alter the approved core architecture without explicit approval.
- Do not make assumptions about governance, access rights, retention, production budget, or school data policy when they are marked TBD.
- Treat student, parent, staff, and school data as sensitive. Never expose secrets, raw PII, or private files in code, logs, tests, commits, or chat output.
- Do not add dependencies, change environment settings, run migrations, deploy, or modify production-facing configuration unless the task explicitly authorizes it.
- Keep each change within the requested scope. Do not combine unrelated refactoring with a feature or bug fix.

## Source of truth

- `CLAUDE.md` contains settled architecture and project-specific working constraints.
- `PROJECT_SPEC.md` and `PROJECT_SPEC_EN.md` are the working bilingual product specifications. Keep both aligned when an approved product decision changes.
- A roadmap or TBD item is not authorization to implement it.
- If documents, code, or user instructions conflict, stop and ask for clarification before making a consequential change.

## Quality gate

For every code change:

1. Add or update the smallest useful automated test.
2. Run the relevant test(s).
3. Run the appropriate static checks for the affected area when practical:
   - PHP: Pint and PHPStan
   - Frontend: ESLint, Prettier check, TypeScript check
4. State clearly what was verified and what was not run, including why.

Use the project’s existing scripts and conventions. Do not create temporary verification scripts when existing tests can prove the behavior.

## Completion and handoff

Before declaring a task complete, provide:

- What changed and why
- Files changed
- Tests/checks run and their result
- Known limitations, risks, or decisions still needed
- The next concrete step, if the task is incomplete

Do not claim a production outcome unless it has actually been deployed and verified in the intended environment.
