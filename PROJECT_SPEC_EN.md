# AI Plus Project Specification

> **Version 2.3 · Code reviewed: 27 October 2026.** Corresponding Vietnamese document: `PROJECT_SPEC.md`. This is a working specification for review and approval, not a claim that production deployment is complete. “Existing” means a route/implementation is present; “Target requirement” requires acceptance testing; “Planned” is not fully implemented; “TBD” needs an authorized decision.

Sections 1–11 summarize the product; Sections 12–24 provide detailed requirements, acceptance criteria and open decisions.

## 1. Overview

AI Plus is LSTS’s internal AI platform for teachers and staff. It supports academic, operational and administrative work through AI chat, specialised Agents, knowledge-file RAG, central administration and work-output generation.

## 2. Objectives

- Provide safe AI assistance in a school environment.
- Enable users to discuss, analyse documents and create content.
- Enable Agents for specific work purposes and school-wide sharing.
- Control access, token quotas and sensitive data.
- Progress from chat to AI-supported work processes.

## 3. Users and permissions

| Group | Primary permissions |
| --- | --- |
| User/Staff | Chat, create personal Agents, upload knowledge, use showcase, view quota and personal artifacts. |
| Admin | Manage users, prompts, templates, showcase, support tickets, audit logs and operational configuration. |

All conversations, attachments, artifacts and email drafts belong to the creating user. Other users cannot read or download these resources without a dedicated sharing mechanism.

## 4. Existing capabilities

### 4.1 AI Chat

- Standalone chat or chat with an Agent.
- Users select GPT-6 Luna (default) or GPT-6 Sol for the next turn; the choice is remembered per conversation and applies to connected specialists and file actions within that turn. Provider access errors never silently switch models.
- Persisted conversations and message history.
- Real-time streaming responses.
- Automatic conversation title generation.
- Chat sidebar ordered by the most recent update.
- Upload images, Word, Excel, PDF, CSV and supported formats.
- PDFs with a text layer are extracted directly; scanned or image-only PDFs are rendered as images for AI vision.

### 4.2 Agent Workspace and RAG

- Create, edit and delete Agents.
- Configure name, description, system prompt and knowledge files.
- Attach an Agent to a conversation.
- Knowledge is chunked, embedded and retrieved by relevance; a configurable keyword fallback is used when embeddings are unavailable.
- Agents can be shared school-wide through Sharing and Showcase.
- Owners may connect up to ten workspace Agents and describe their roles. The coordinator selects useful specialists through tool calling; each runs at most once per user turn using its own prompt and Knowledge, then returns findings. Specialists cannot call further Agents or create files directly.
- Coordinator chats show the connected Agents accessible to the current user on a single, horizontally scrollable line immediately below the model label. This lists available connections, not Agents confirmed to have been called for a particular reply.
- Permission is checked for the person chatting at every invocation. Connections do not grant access to private Agents. Shared coordinators may use only specialists their current user may also use. Copies do not inherit connection settings; recipients configure their own connections.
- All model calls count toward the chatting user's quota, including calls completed before a later synthesis failure. Limits are ten consultations, a 32,000-token threshold and 300 seconds (five minutes) for coordination per turn. Each call is bounded by the current model timeout and the remaining coordination time. No further calls start after a limit is reached; usage from an in-flight request may exceed the token threshold. Only the coordinator executes file tools after the existing user-intent confirmation.
- Setting `AI_PLUS_AGENT_CONNECTIONS_ENABLED=false` restores the existing chat flow without deleting connections or history. An unmigrated connection table also leaves the existing flow available.

### 4.3 Sharing and Showcase

- An Agent selected as Share with school appears in Sharing and Showcase.
- Shared Agents support `use_only` and `copy`; copying an Agent also copies knowledge only when its owner explicitly chose `copy` access.
- New marks recent content; Popular marks Agents with substantial usage.

### 4.4 Admin Panel

- Manage users, roles, departments and account status.
- Manage prompts, templates, showcase content, FAQs and support tickets.
- Audit logging for administrative actions.
- Admin shortcut from AI Plus.

### 4.5 Usage quota

- Prompt, actual-token, AI+ Credits and recent-activity tracking.
- Quota is calculated over a monthly cycle beginning on the first day or a later phase start date; usage logs do not have to be erased to “reset” it.
- Testing: 20,000 credits per user per month, converted from the previous 20-million-token allowance.
- Training: 10,000 credits per user per month, converted from the previous 10-million-token allowance.
- 1,000 Luna tokens = 1 credit; 1,000 GPT-6 Sol tokens = 20 credits, including input and output. Credits are an internal allowance, not money or OpenAI credits. Integer units preserve 0.001-credit precision without rounding up each turn. Image tokens retain their former allowance value; Sol multipliers are not image-pricing conversion rates.
- Legacy usage is not repriced: rows without `quota_units` retain their original token total. New rows record the model and a `quota_units` snapshot; disabling Sol does not change recorded charges. Existing quota overrides and token environment settings retain their storage units to preserve balances. Admin forms display/accept credits; actual-token statistics remain separate.
- Deleted conversations do not appear in Recent Activity.

### 4.6 Image Workspace

- Create and edit images from prompts/reference images using a separately configured image model.
- Outputs belong to conversations and have owner-checked download and deletion routes.

### 4.7 School Knowledge and safe use

- Admins add school documents/public pages, index them and monitor source state.
- Users accept the current AI Policy version; work-use monitoring records limited metadata for cases needing review.
- Microsoft login routes exist; availability depends on real tenant configuration.

## 5. AI Tools and Artifacts Phase 1

### 5.1 File creation

When a user asks to create or export Excel, Word, PowerPoint or PDF from the current discussion, AI Plus creates a private artifact for that user.

| Type | Format | Purpose |
| --- | --- | --- |
| Excel | `.xlsx` | Summaries, datasets, worksheets and spreadsheet reports. |
| Word | `.docx` | Notices, plans, reports and formal documents. |
| PowerPoint | `.pptx` | Slide presentations with titles and concise bullet points. |
| PDF | `.pdf` | Final files for distribution or archiving. |

Chat also accepts individual text-based source files (`.js`, `.css`, `.ts`, `.json`, `.xml`, `.md`, `.php`, `.py`, `.java`, `.sql`, and other common formats). AI can review/edit them and return a new file with the same extension; the source file is never overwritten. Source code is handled as text and is never executed. Each chat file is limited to 5 MiB, and at most 20,000 characters are included in AI context. For edits, AI returns small patches; the server applies each exact, unique patch to the private original and creates a complete output file, preserving all unchanged content. If a patch does not match exactly one location, no file is created and the user is asked to clarify. Files exceeding the visible context are not considered fully reviewed by AI.

Processing flow:

1. The user discusses work with AI or uploads a document.
2. The user requests a file, for example: `Create a Word file using the content above`.
3. AI generates the response content.
4. The backend creates a private artifact linked to the user and conversation.
5. The chat displays a download link.
6. The artifact appears in recent-file history.

The original user file is not overwritten. Each request creates a new artifact.

### 5.2 Email drafts

When a user requests `Draft an email`, the system saves the AI-generated subject and body. An email draft is only a draft: Phase 1 does not send email, connect a mailbox or send content outside the system.

### 5.3 Streaming and audit

- SSE reports AI response and file or email-draft generation states.
- `ai_artifact.created` and `email_draft.created` actions are audit logged.
- Artifact download checks ownership before returning the file.

## 6. Main data model

| Entity | Role |
| --- | --- |
| `users` | Accounts, roles, departments and active state. |
| `conversations` | Conversations, optionally associated with an Agent. |
| `messages` | User and assistant messages with token usage. |
| `agents` | User Agents, system prompts and knowledge. |
| `knowledge_chunks` | Knowledge chunks used by RAG. |
| `usage_logs` | Token usage and activity. |
| `ai_artifacts` | AI-created files: owner, conversation, path, MIME type and size. |
| `email_drafts` | Draft emails: owner, conversation, recipients, subject, body and attachment metadata. |
| `admin_audit_logs` | Administrative and AI tool events. |

## 7. Important API routes

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/ai-plus/agent-workspace/send` | Non-streaming chat. |
| POST | `/ai-plus/agent-workspace/send-stream` | SSE streaming chat. |
| GET | `/ai-plus/artifacts/{artifact}/download` | Download an artifact when the requester is its owner. |
| GET | `/ai-plus/agent-workspace/attachments/{conversation}/{filename}` | Download a chat attachment when the requester is its owner. |

## 8. Security and privacy

- Authentication is required for functional AI routes.
- Ownership is enforced for conversations, attachments and artifacts.
- Attachments and artifacts use private storage and are not directly public.
- Login, chat, Agent uploads and support requests are rate limited.
- AI Markdown is sanitised before rendering; dynamic admin content is escaped to reduce XSS risk.
- The model cannot execute arbitrary system commands; every action must use a defined backend tool.

### 8.1 PII filtering

PII is filtered before content is sent to AI, including:

- Email outside `@lsts.edu.vn`.
- Vietnamese phone numbers.
- Student identifiers.
- Citizen and national identity numbers.
- Specific addresses.
- Bank accounts, passports and vehicle licence plates.

Exact `@lsts.edu.vn` email is allowed for internal work. Raw PII is not stored in audit logs.

## 9. Current limitations

- The platform does not send real email.
- It does not connect to Microsoft 365, OneDrive, Google Drive or Gmail.
- It does not edit the user’s original file directly.
- Artifacts are currently generated from AI response content; advanced Word and Excel templates are a later improvement.
- Conversation export also supports HTML, language selection and document styles; these are output structures rather than official school Word/Excel templates.
- Scanned PDF rendering is limited by configuration to control time and cost.
- Large file jobs currently run in the chat request and should move to queues in production.

## 10. Roadmap

### Phase 1 Current

- Create Excel, Word and PDF.
- Email drafts.
- Private artifact download, history and audit log.
- Streaming processing states.
- Scanned PDF fallback.

### Phase 2 Server deployment

- Microsoft 365 and OneDrive integration.
- Save generated artifacts to OneDrive.
- Create Outlook email drafts.
- User confirmation before sending email.
- Queue workers, Redis or object storage and virus scanning.

### Phase 3

- Send real email after user confirmation.
- Multi-step workflows: read file → create report → create email → user confirms.
- Official Word and Excel templates.
- Tool permissions by role and department.
- Tool-cost, usage and advanced audit dashboards.

## 11. Server deployment requirements

- HTTPS and a reverse proxy correctly configured for SSE streaming.
- Laravel scheduler; queue workers when background tasks have actually been queued.
- Production database, database backup and file storage backup.
- Private object storage or a storage server.
- Poppler (`pdftoppm`) for scanned PDFs.
- Appropriate PHP and Nginx upload limits.
- `.env` configuration, cache/config/route optimisation and log monitoring.
- File-retention and temporary-file cleanup policies, backup and error handling.

## 12. Document control and scope boundary

| Attribute | Description |
| --- | --- |
| Product | AI Plus (AI+), the internal AI platform of Lawrence S. Ting School (LSTS) |
| Lead unit | CIEC; final product owner, data owner and governance model: **TBD** |
| Audience | School leadership, product owner, IT, developers, QA, admins and data stewards |
| Related material | `PROJECT_SPEC.md`, `docs/DEPLOYMENT_CHECKLIST_VI.md`, `docs/openai-integration.md`, repository code and migrations |
| Interpretation | This spec states requirements; code shows the current implementation; the deployment checklist provides operations steps. Differences must be recorded as gaps and resolved. |

Every scope change must update both language versions, revision date, related decision and acceptance criteria. The roadmap is not a committed schedule. Success measures to define before the pilot: activated and weekly active users; completed chat and export rates; true/false PII blocks; token/cost against budget; support ticket volume and resolution time. Owners, measurement sources and targets: **TBD**.

### 12.1 In and out of scope

- **Current scope:** chat, Agents/RAG, School Knowledge, Prompt Library, Agent Templates, Sharing and Showcase, Image Workspace, quotas/My Usage, support, administration, file export and email drafts.
- **Outside current scope:** sending email; mailbox access; OneDrive/Google Drive/Gmail sync; overwriting source files; autonomous multi-step workflows; guarantees that AI content is always correct or that complex source layouts are preserved.
- **Environment dependencies:** Microsoft SSO needs an App Registration and secrets; scanned PDFs need Poppler; OCR needs its runtime; JavaScript-heavy page reading needs Playwright/Chromium; AI features need the relevant model and API entitlements. Code or a route alone does not establish production readiness.

## 13. Architecture, processing and technology

| Layer | Current design |
| --- | --- |
| Frontend | Laravel Blade plus React/Inertia and Vite |
| Backend | PHP `^8.3`, Laravel `^13.17`, controllers/services/middleware; Laravel queue and scheduler |
| AI | OpenAI API for chat, embeddings, configurable moderation and image generation; models/timeouts configured through the environment |
| Data | Eloquent/migrations; MySQL is the production target; local/test configuration may differ |
| Files | Separate private `knowledge`, `chat-attachments`, `agent-avatars` and `ai-artifacts` disks |
| Documents | PhpWord, PhpSpreadsheet, DomPDF and a PDF parser; Poppler for scanned PDFs |

**Chat flow:** browser → route with authentication/AI policy/throttling → input and PII validation → safety and quota checks → history, attachments, Agent/School Knowledge and a public web page if the user supplied a URL → OpenAI → persist messages/usage → create requested artifacts/drafts → return JSON or SSE. Material from files and web pages is untrusted data, not a system instruction.

## 14. Testable functional requirements

These IDs supplement the feature descriptions in Sections 4–7. “Existing” is based on code inspection and still requires staging verification.

| ID | Requirement and acceptance criterion | Status |
| --- | --- | --- |
| FR-01 | Only authenticated, active users who accepted the current AI Policy version can use AI+ modules; both the first-use confirmation page and the in-app AI Policy & Guidelines page allow switching between English and Vietnamese; direct access without a prerequisite is denied or redirected appropriately. | Existing |
| FR-02 | Admin routes require the admin role; important actions record actor, action, target and time. Admin status does not itself grant access to private user chats/files. | Partly existing; audit matrix needed |
| FR-03 | Users create standard/Agent chats, view history, rename, move and delete; another user cannot access them by guessing IDs. Deleted conversations do not appear in Recent Activity. | Existing |
| FR-04 | Chat supports JSON and SSE; the client shows incremental text, errors and completion. A disconnected stream is not treated as a completed answer. | Existing; verify through production proxy |
| FR-05 | Chat accepts at most 5 attachments per turn, 15 MiB combined; at most 4 direct images, 4 MiB each; documents and text-based source files up to 5 MiB each; source context up to 20,000 characters; messages up to 5,000 characters. Source code is never executed; oversized source files are not exported partially. Limits produce understandable errors. | Existing |
| FR-06 | An Agent has an owner, name, description, system prompt, avatar and knowledge; only its owner edits/deletes it. My Agents uses a consistent default card height, reserves a three-line description area, truncates long descriptions and provides an inline More/Less toggle that expands only the selected card. Knowledge is capped at 10 files, 15 MiB per file and 50 MiB per Agent. | Existing |
| FR-07 | Knowledge is indexed/reindexed and retrieved by relevance; embedding failure may fall back to keywords. AI must not imply that knowledge supports an answer when no relevant passage was found. | Partly existing; quality assessment TBD |
| FR-08 | Admins can add supported files/public `lsts.edu.vn` pages to School Knowledge, see `processing/ready/failed`, sync, delete and enable/disable use. | Existing |
| FR-09 | Shared Agents have `use_only` or `copy` access; knowledge is copied only with owner permission. Revocation blocks new use; rights to existing copies require explicit testing. | Existing; authorization tests needed |
| FR-10 | Prompt Library and Agent Templates show only `published` content. Each item is classified under level-one `Teaching` or `School Operations` and an appropriate level-two category. The default published catalog is curated: prompts include useful context and a requested output, while Agent Templates have usable system prompts; older unselected entries remain draft/archived without deleting history or use counts. Admins manage status and taxonomy; public filters support both levels. Showcase offers search/filter, views/uses, comments and owner/admin deletion controls. | Implemented after migration; QA required |
| FR-11 | Image Workspace creates/edits images from a prompt and up to 4 reference images; output belongs to a conversation and only its owner may download/delete it. | Existing |
| FR-12 | File creation produces a new private artifact linked to user/conversation without overwriting the source; DOCX/XLSX/PPTX/PDF/HTML and source-code files with their original extension are supported. PowerPoint must be a valid `.pptx` package with content organized into slides, titles and bullets, not only text in chat. HTML webpage requests must produce a complete standalone HTML document; prose must not be saved as an HTML file. Conversation export also supports PPTX/HTML, source/VI/EN language and standard/report/lesson plan/meeting minutes/budget styles. | Existing |
| FR-13 | Artifact downloads/deletion and attachment/image access enforce ownership on the server; missing files return 404 and cross-user access is denied. | Existing |
| FR-14 | “Draft an email” only saves AI-generated subject/body linked to the user/conversation; it neither sends mail nor reads a mailbox. Recipients/attachments are not automatically verified. | Existing |
| FR-15 | My Usage shows only the user's data; Credits sum `SUM(COALESCE(quota_units, prompt_tokens + completion_tokens))/1000` in the monthly/phase cycle with a per-user override; exhausted quota returns 429. | Existing |
| FR-16 | Users submit/track support tickets and may attach up to 5 supported images/files (10 MiB per file, 15 MiB total); only the ticket owner and admins can download them from private storage. Admins can post public replies with up to 4 JPG/PNG/WebP images (4 MiB each, 16 MiB total), pasted into the reply box or selected as files; images are viewable only by the ticket owner and admins, are not emailed, and are deleted with the ticket. Initial request files are not emailed; deleting a ticket also deletes its attachments. Admins see an automatically refreshed “New” badge for Pending tickets; it stops when a ticket moves out of Pending. Admin replies are public replies in ticket history and are not emailed. | Partially implemented; local QA required |

**Current default quotas:** Testing 20,000 and Training 10,000 credits per user per month; the cycle begins at month start or later phase start. Credits are **not** a monetary or image-generation spending cap. Input/output and images have different prices; an in-flight request may exceed the balance, with no concurrent budget reservation. Production phase, budget and cost alerts: **TBD**.

## 15. Data, ownership and lifecycle

| Group | Content | Lifecycle/control point |
| --- | --- | --- |
| `users`, `teams` | Accounts, roles, status, teams, quota, policy acceptance | Disabling an account must not expose prior data; user deletion needs a policy |
| `conversations`, `messages` | Chats/images, messages and Agent association | Owner-based access; consistent chat/attachment/image deletion |
| `agents`, `knowledge_chunks` | Agent configuration, knowledge files and index | Owner, access mode, reindex on file changes |
| `school_knowledge_sources/chunks` | School-wide source material and index | Admin management, sync/failure state, source/chunk deletion |
| `usage_logs`, `ai_safety_events` | Tokens/activity, work-use classification and moderation metadata | Usage drives quotas; safety events pruned after 180 days |
| `ai_artifacts`, `email_drafts` | Private output files and drafts | User FK; conversation may become null when chat is deleted; artifact deletion must not be assumed |
| Showcase, support, `admin_audit_logs` | Posts/interactions, tickets/replies and audit | Only published posts visible to users; retention TBD |

**Target data requirement:** set retention separately for conversations, attachments, images, knowledge, artifacts, drafts, usage, audit and backups; clean up both DB and file/object storage; support legitimate export/deletion requests; encrypt backups and rehearse restoration. At present, only the 180-day `ai_safety_events` pruning period is explicit.

## 16. Security, PII and AI safety

| ID | Requirement and acceptance criterion | Status |
| --- | --- | --- |
| SEC-01 | Enforce authentication, active account, policy and ownership on the server; direct cross-user ID/URL access must be denied. | Existing; mandatory regression |
| SEC-02 | Scan input that may be stored/sent to AI for external email, Vietnamese phone numbers, student IDs, national IDs, addresses, bank accounts, passports and plates. Some flows redact; chat may return 422. Detection is not guaranteed to be perfect. | Existing; false-positive/negative tests needed |
| SEC-03 | Default `@lsts.edu.vn` whitelisting is a configuration choice, not proof that every mailbox under that domain is non-personal. Data owner/IT must approve it before go-live. | TBD |
| SEC-04 | Audit/monitoring must not retain raw PII, sensitive prompts or API keys; production logs need periodic review. | Target requirement; audit implementation |
| SEC-05 | The work-use classifier supports monitoring; in the current mode only moderation/local safety rules block unsafe input, while “personal/ambiguous” classification does not block automatically. Events contain limited metadata and are pruned after 180 days. | Existing; Moderation API depends on key entitlement |
| SEC-06 | Sanitize AI Markdown/HTML, validate uploads, prevent SSRF in URL reading, keep files private, and verify CSRF/session/TLS/rate limits. | Partly existing; go-live hardening |
| SEC-07 | The model must not execute system commands or invoke tools outside defined backend actions; prompts in files/web/knowledge must not override system instructions. | Target requirement; prompt-injection tests |

**Proposed data classes:** public (school website), internal (prompts/templates), restricted (chats, knowledge, attachments, drafts/artifacts) and especially sensitive (student, parent and personnel data). The data owner must approve what can be sent to an external AI provider, the legal basis, retention and deletion process. Internal documents are not automatically approved for external API processing.

## 17. Interfaces and error conventions

These are internal web-app routes, **not a versioned public API**. Consult controllers, Form Requests and tests for exact request/response contracts before external integration.

| Method | Route | Result/constraint |
| --- | --- | --- |
| POST | `/ai-plus/agent-workspace/generate-image` | auth + policy + image throttle; image URL/ID |
| POST | `/ai-plus/agent-workspace/conversations/{conversation}/export` | Owner only; format/language/template; download URL |
| DELETE | `/ai-plus/artifacts/{artifact}` | Owner only; deletes private file and metadata |
| PATCH/DELETE | `/ai-plus/agent-workspace/conversations/{conversation}` | Owner rename/delete; `/move` route moves it |
| CRUD | `/ai-plus/agent-workspace/agents...` | Owner manages Agents; sharing mode governs use/copy |
| GET/POST | `/ai-plus/sharing-showcase...`, `/ai-plus/support...` | Published content and authorized tickets |
| CRUD | `/admin/...` | auth + policy + admin middleware |

Target error convention: 401/login redirect for unauthenticated requests; 403/404 for unauthorized access depending on route; 422 for validation/PII; 429 for quota/rate limit; 502 for provider failures. The client must distinguish retryable failures from input corrections. SSE may return JSON before opening a stream on validation failure; only `done` confirms completion.

## 18. Non-functional requirements

| ID | Requirement | Verification/status |
| --- | --- | --- |
| NFR-01 | Site-wide HTTPS, secrets outside Git, least privilege, private disks inaccessible via the web server. | Production configuration and direct-URL checks |
| NFR-02 | UI shows processing state; proxy does not buffer SSE. Ordinary chat retains its 120-second PHP setting; connected Agents receive 300 seconds for coordination plus one model timeout for output completion (420-second PHP setting by default). Configure suitable outer PHP-FPM/proxy limits; 450 seconds is recommended for these defaults. | Smoke test through real domain on slow networks |
| NFR-03 | Clear failures when OpenAI/Poppler/OCR/Playwright is unavailable; fallback only where semantically valid. | Dependency-failure tests; uptime/SLO TBD |
| NFR-04 | Move heavy tasks to queue with controlled retries/idempotency before scaling; some work currently runs in the chat request. | Pilot load test; trigger threshold TBD |
| NFR-05 | Monitor errors, latency, failed jobs, disk, usage and API cost without logging sensitive data. | Dashboard/alerts/on-call owner TBD |
| NFR-06 | Back up DB and private files, rehearse restores and verify integrity. | RPO/RTO, backup schedule/retention TBD |
| NFR-07 | Clearly show quota, upload limits, block reasons and status; support VI/EN content and basic keyboard navigation. | Staff UAT; accessibility standard TBD |
| NFR-08 | Work with school-supported browsers/OS and common Word/Excel/PDF readers. | Version matrix TBD |

## 19. Testing, acceptance and release

1. Unit/feature tests for authentication, policy acceptance, role and ownership across conversations, Agents, attachments, images, artifacts, Showcase and support.
2. VI/EN PII cases: valid/sensitive data, batch student IDs, attachments, false positives and whitelist; verify no raw PII in logs.
3. JSON/SSE chat: provider timeout/error, stream disconnect, near/exhausted quota, usage recording and unintended duplicate artifact prevention.
4. Import/index/reindex/remove Agent and School Knowledge; text/scanned PDFs, OCR present/absent and embedding fallback.
5. Open DOCX/XLSX/PPTX/PDF/HTML output; verify Vietnamese text, safe filenames, access/deletion controls and inability to send drafts.
6. Local/Microsoft login in the pilot environment, logout/session, rate limit, CSRF, SSRF and prompt injection.
7. Run the test suite, lint/type checks and migrations on staging; smoke test through production-equivalent HTTPS/proxy. Rehearse backup/restore.

**Proposed acceptance gate:** no unresolved P0/P1 defects; all cross-user paths denied; backup/restore proven; product/data owners sign off UAT, data policy and go-live. Quantitative thresholds for performance, RAG quality, SLA and accepted defects must be approved separately before the pilot.

## 20. Deployment and operations

Prepare Linux, PHP/Composer, Node for builds, production DB, HTTPS/reverse proxy and private storage. Install Poppler; enable OCR/Chromium only after testing. Configure `.env`/secrets, `APP_DEBUG=false`, secure cookies, SSO callback, OpenAI key/models, phase/quota, uploads and timeouts. Run migrations, build assets, optimize Laravel and schedule every minute. A queue worker is required once heavy work is queued. Check SSE through the proxy, storage permissions, normal/admin accounts and denial of direct private-disk access. Set up backups, alerts and runbooks for provider outage, quota exhaustion, full disk, indexing failures and rollback. Detailed steps are in `docs/DEPLOYMENT_CHECKLIST_VI.md`.

## 21. Risks and open decisions

| ID | Issue/impact | Required decision |
| --- | --- | --- |
| D-01 | Final governance, product/data owners and admins are unsettled | School leadership approves RACI and permissions |
| D-02 | Whitelisted domain may include personal mailboxes | Data owner/IT verifies and revises rules |
| D-03 | Retention beyond safety events, export/deletion and backup policy unset | Approve policy by data type |
| D-04 | SSO/Outlook/OneDrive need tenant permissions and configuration | IT grants access and tests App Registration |
| D-05 | Moderation API may return 403; local fallback catches narrow patterns | Confirm entitlement and unavailable-service behavior |
| D-06 | Large files run in requests and depend on installed binaries | Measure load and move heavy work to queue |
| D-07 | Token quota is not a monetary/image spending cap | Budget cap, model pricing and alerts |
| D-08 | RAG/AI can err; sources can contain prompt injection | Evaluation set, human review and red-team tests |
| D-09 | SLO, RPO/RTO, browser/accessibility targets unquantified | Agree targets based on pilot and IT capacity |

## 22. Revision history

| Version | Date | Change |
| --- | --- | --- |
| 1.x | 18 Sep 2026 | Short, 11-section description |
| 2.0 | 30 Sep 2026 | Added scope/status, FR/NFR, data, acceptance, operations, risks and open decisions; aligned English and Vietnamese versions |
| 2.1 | 16 Oct 2026 | Added PowerPoint (`.pptx`) generation and conversation export |
| 2.2 | 23 Oct 2026 | Added owner-configured Agent connections, execution limits, permission checks, usage accounting and rollback switch |
| 2.3 | 27 Oct 2026 | Added Luna/Sol selection, AI+ Credits, preserved legacy balances/history and snapshotted quota charges |

## 23. End-to-end business scenarios

| ID | Preconditions and action | Accepted result; error case |
| --- | --- | --- |
| UC-01 Private chat | Active user has accepted policy and has quota; creates a chat, sends a prompt, continues, renames and deletes it. | Only the owner sees history; usage increases after a successful AI turn; stream has `done`; deleted chat disappears from sidebar/Recent Activity. Empty prompt, PII and exhausted quota receive suitable errors. |
| UC-02 Agent with knowledge | Owner creates an Agent, uploads valid knowledge, asks questions inside/outside the source, edits/removes files. | Index updates; relevant passages inform answers where present; the AI does not claim source support without relevant retrieval. Oversized/corrupt files fail clearly without losing valid existing sources. |
| UC-03 Share an Agent | Owner chooses `use_only` or `copy`; another user discovers and uses it. | `use_only` does not transfer knowledge ownership; `copy` creates a recipient-owned Agent and copies files only when permitted. Recipients cannot edit the original; revocation blocks new use. |
| UC-04 Export a file | User discusses content, creates Word/Excel/PDF or exports a conversation, downloads then deletes the artifact. | File opens and belongs to the user; metadata and download agree; source is not overwritten. Another user with the same ID is denied; failed generation does not return a broken success link. |
| UC-05 Email draft | User requests an email draft from the conversation. | Subject/body are saved for the owner and audited; no email is sent and no mailbox is connected. User reviews content before using it elsewhere. |
| UC-06 Admin and support | User submits a ticket; admin handles it, updates School Knowledge and reviews usage/safety events. | User sees only their tickets; admin role and audit are enforced; index failure remains visible for repair; scheduler removes expired safety events. |

## 24. Configuration, dependencies and change control

| Configuration group | Current value/behavior | Confirm before production |
| --- | --- | --- |
| `AI_USAGE_PHASE`, `AI_*_TOKEN_LIMIT`, `AI_USAGE_PHASE_STARTED_AT` | Default phase `testing`; 20M/10M token limits | Real phase, user overrides, budget and alerts |
| `OPENAI_MODEL`, `OPENAI_IMAGE_MODEL`, `OPENAI_EMBEDDING_MODEL` | Separate chat/image/embedding models | Entitlement, data region, price, quality and fallback |
| `OPENAI_MODERATION_ENABLED` | Configurable; local safety fallback is limited | Behavior on 403/timeout; policy approver |
| `PDFTOPPM_BINARY`, `PDF_SCAN_MAX_PAGES` | Default scanned PDF cap is 10 pages | Binary, longer documents, timeout and user message |
| OCR and Playwright/Chromium | Require binaries/runtime and may be absent on server | Test pilot file/URL types on target environment |
| `PII_EMAIL_DOMAIN_WHITELIST` | Defaults to `lsts.edu.vn` | Verify domain does not exempt personal mailboxes requiring protection |
| Storage and proxy | Private disks, SSE and uploads depend on web server | Check file rights, backups, body limits, buffering and TLS |

**Change control:** changes to models, data limits, sharing rights, retention, PII policy or integrations require a proposer, privacy/cost impact review, regression tests, rollback plan and named approver. Ticket mechanism, approver and release versioning: **TBD**.
