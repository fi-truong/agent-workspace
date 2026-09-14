# CLAUDE.md — Agent Workspace (CIEC AI+ / LSTS)

> File này để Claude Code đọc tự động khi mở project. Giữ ngắn gọn, cập nhật khi có quyết định mới.

## Bối cảnh dự án

**Agent Workspace** là module cốt lõi của **AI+ (Internal AI Environment)** — sáng kiến do
**CIEC (Center of Innovation, Entrepreneurship and Creativity)** dẫn dắt tại
**Lawrence S. Ting School (LSTS)**, trường K-12 song ngữ Việt–Anh tại Việt Nam.

- **Người phụ trách:** Trương Minh Fi (Fi) — CIEC Coordinator 05, EdTech Focus. Build **solo**,
  dùng **Claude Code**.
- **Người phê duyệt cuối:** HOS Mr. Chen Wei-Hung — đã chỉ đạo dùng **Codex/OpenAI** (không dùng Claude)
  cho AI Training Program, và chốt **OpenAI API pay-as-you-go** (không dùng ChatGPT Business seats)
  làm hướng kỹ thuật cho Agent Workspace.
- Đây là **project MỚI HOÀN TOÀN**, KHÔNG dùng chung codebase với website trường (PHP 7.3 / Laravel 8.5,
  cả hai đều EOL, không có Git). Không copy code, không tham chiếu dependency từ project cũ.

## Kiến trúc đã chốt (không đổi trừ khi Fi yêu cầu)

Đã so sánh 3 phương án — **Option 1 (API-Based Integration, gọi trực tiếp OpenAI API)** được chọn
vì chi phí thấp, độ phức tạp vừa phải, phù hợp quy mô trường học. KHÔNG build model riêng,
KHÔNG dùng Enterprise AI Platform (Azure AI Foundry/Bedrock/Vertex) ở giai đoạn này.

## Tech Stack

| Layer | Công nghệ | Ghi chú |
|---|---|---|
| Frontend | React (nếu cần dashboard giàu UI — My Usage, charts) hoặc Blade + Alpine.js (nhẹ hơn) | Chọn theo từng phần, không bắt buộc 1 framework cho toàn bộ |
| Backend | **PHP — Laravel bản mới nhất được hỗ trợ chính thức** | Máy dev hiện chạy **PHP 8.5.9** → phải dùng **Laravel 13** (hỗ trợ PHP 8.3–8.5); Laravel 12 chỉ hỗ trợ đến PHP 8.4, sẽ lỗi composer dependency |
| Guardrail (PII filtering) | Regex pattern (PHP) + OpenAI Moderation API | Bắt buộc có **automated test** — không dựa vào test thủ công vì đụng dữ liệu HS/PH |
| Auth/SSO | Laravel Socialite + Microsoft/Azure provider (Entra ID, OAuth2/OIDC) | Cấu hình App Registration thật cần quyền admin Azure — làm sau khi có Global Admin M365 |
| Database | **MySQL** | Nhất quán với CRM4, dễ tích hợp sau này |
| Version control | **Git — bắt buộc từ commit đầu tiên** | Không lặp lại lỗi "quản lý qua Google Drive" của website cũ |
| CI/CD | GitHub Actions | Từ Sprint 1, không để dồn về sau |
| Hosting | Chia sẻ server website hoặc server riêng | Cần Huy/Ngọc đánh giá tải server hiện tại trước khi quyết |

**Nguyên tắc bắt buộc:**
1. Git từ commit #1, không "để sau".
2. CI/CD dựng từ Sprint 1.
3. Guardrail phải có automated test tối thiểu.
4. KHÔNG chia sẻ codebase với website cũ (PHP 7.3/Laravel 8.x, EOL).
5. Nếu sau này website được viết lại và cũng dùng Laravel → có thể gộp chung MySQL DB
   và Auth/SSO để giảm tải bảo trì cho team IT 2 người (Huy/Ngọc) — nhưng đó là quyết định tương lai,
   chưa áp dụng bây giờ.

## Cấu trúc AI+ (bối cảnh rộng hơn Agent Workspace)

AI+ có 7 mục, nằm trong staff portal (lsts.edu.vn): **Agent Workspace** (module này),
Sharing & Showcase, Prompt Library, Agent Templates, AI Policy & Guidelines, My Usage, Support.
Truy cập theo vai trò (role-based): Staff/Teacher xem được rộng nhất.

## Mô hình chi phí (tham khảo, không phải logic cần code cứng)

- Pricing basis: **GPT-5.6 Luna** — $0.20/1M input tokens, $1.20/1M output tokens (xác nhận 30/7/2026,
  cần kiểm tra lại giá khi triển khai thật vì giá API có thể đổi).
- Giả định input/output 60/40 — đây là **giả định làm việc, chưa có nguồn xác thực chính thức**,
  cần hiệu chỉnh lại sau khi có dữ liệu Pilot thật.
- 3 giai đoạn dân số dùng: CIEC nội bộ (~4 người) → Training/Pilot (~25 người) → Official (toàn trường,
  quy mô ~2,200 người nhưng active/day thấp hơn nhiều).

## Việc CHƯA làm / còn chờ

- Cấu hình App Registration Entra ID thật — chờ Fi có quyền M365 Global Admin.
- Governance model cho AI+ (ai quản trị hệ thống) — 4 phương án A/B/C/D đã đề xuất, **HOS chưa chốt**.
  Không tự giả định phương án nào khi code phần quản trị/phân quyền.
- Server hosting (chung hay riêng với website) — chờ Huy/Ngọc đánh giá tải.

## Quy ước làm việc với Claude Code trong project này

- Đây là session/CLAUDE.md **riêng biệt** với workspace `ciec-workspace` (nơi Fi làm các tài liệu
  CIEC khác). Không trộn ngữ cảnh.
- Khi không chắc một quyết định kỹ trúc/kỹ thuật đã được chốt hay chưa, hỏi lại Fi thay vì tự suy đoán —
  đặc biệt với phần đụng dữ liệu học sinh/phụ huynh (PII) và phần quản trị quyền (governance).
- Ưu tiên đúng tech stack đã chốt ở trên; nếu đề xuất khác đi (vd đổi ORM, đổi queue system),
  nêu rõ lý do và hỏi trước khi áp dụng.
- File naming pattern cho tài liệu liên quan (không phải code): `CIEC_[YY]_[MMDD]_[Ten_Mo_Ta].docx`.

## Lịch sử phiên bản
| Phiên bản | Ngày | Thay đổi |
|---|---|---|
| v1.0 | 2026-08-18 | Khởi tạo, dựa trên SKILL.md ciec-ai-plus-lsts |

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>
