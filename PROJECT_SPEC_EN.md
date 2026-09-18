# AI Plus Project Specification

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
- Keyword RAG retrieval from Agent knowledge.
- Agents can be shared school-wide through Sharing and Showcase.

### 4.3 Sharing and Showcase

- An Agent selected as Share with school appears in Sharing and Showcase.
- Other users can use that Agent to create a copy they own.
- New marks recent content; Popular marks Agents with substantial usage.

### 4.4 Admin Panel

- Manage users, roles, departments and account status.
- Manage prompts, templates, showcase content, FAQs and support tickets.
- Audit logging for administrative actions.
- Admin shortcut from AI Plus.

### 4.5 Usage quota

- Prompt, token-usage and recent-activity tracking.
- Monthly quota reset on the first day of the month.
- Testing: 20 million tokens per user per month.
- Training: 10 million tokens per user per month.
- Deleted conversations do not appear in Recent Activity.

## 5. AI Tools and Artifacts Phase 1

### 5.1 File creation

When a user asks to create or export Excel, Word or PDF from the current discussion, AI Plus creates a private artifact for that user.

| Type | Format | Purpose |
| --- | --- | --- |
| Excel | `.xlsx` | Summaries, datasets, worksheets and spreadsheet reports. |
| Word | `.docx` | Notices, plans, reports and formal documents. |
| PDF | `.pdf` | Final files for distribution or archiving. |

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
- Laravel queue workers and scheduler.
- Production database, database backup and file storage backup.
- Private object storage or a storage server.
- Poppler (`pdftoppm`) for scanned PDFs.
- Appropriate PHP and Nginx upload limits.
- `.env` configuration, cache/config/route optimisation and log monitoring.
- File-retention and temporary-file cleanup policies, backup and error handling.
