# Design — v3.0.0: enforce by default and the end of per-path security

- **Ticket:** AID-733 (continues AID-632, closed)
- **Date:** 2026-07-30
- **Status:** implemented and shipped as **v3.0.0** (tag `v3.0.0`, commit `b19aff1`, 2026-08-03) — sections 1-10 as amended by section 11 and by the dated in-place amendment to 11.5 (Stringable trust, 2026-08-03). The two decisions 11.7 left open were taken by the owner on 2026-08-03: absolute 2.x EOL 2027-02-03; minimum 2.x patch severity high (CVSS ≥ 7.0), plus any severity when the vector is data exposure through templates. Governance record: `approvals/majors/mustache-3.md` in the central workspace
- **Baseline:** v2.1.0 (tag `v2.1.0`, commit `2a3db11`, published on Packagist with `dist`)

## 1. Context

v2.1.0 shipped as a non-breaking bridge: the `SecurityValidator` is now wired into every resolution path, but it runs in `report` mode, logging what it *would* block instead of blocking it. v3.0.0 is the other half — it flips the default to `enforce` and closes the findings that could not ship without breaking behaviour.

The original plan gated v3 behind a 2-4 week observation window with the only known consumer. That gate is withdrawn (see the amendment recorded in `CLAUDE.md` on 2026-07-30): once a consumer pins `^2.0`, a `v3.0.0` tag never reaches it, so there is nothing to wait for. Report-mode warnings are gathered *alongside* this work as input for the upgrade guide, never as a precondition.

## 2. Threat model

The package cannot know who authors the templates it resolves, and the evidence says nobody is checking. The reference consumer stores templates through direct database writes that bypass its own application layer, and performs no validation of what goes in. Under those conditions a template is untrusted data by construction — no attacker needs to be postulated.

The consuming software "just translates". That makes the package the last place where anyone can look, which is what justifies sanitising by default rather than deferring the decision to configuration nobody sets.

**Posture:** block by default, let the consumer open specific doors on purpose, and make every block visible in the log.

### What makes this safe to adopt

Blocking and failing are already decoupled, verified against the current suite:

```
enforce + blacklist 'email'
  'Email: {{User.email}} / Name: {{User.name}}'  →  'Email:  / Name: John Doe'
```

`ResolutionPipeline` only throws when *no* resolver supports a token; a resolver returning `null` yields an empty string. A blocked attribute therefore leaves its own placeholder empty and the rest of the template resolves normally. Turning on enforcement does not take pages down.

## 3. The defect being fixed

Three known bypasses share one root cause:

- **`CollectionResolver`** navigated through `getRaw()`, skipping the accessor entirely (found pre-landing in 2.1.0, closed with `SecurityAwareAccessorInterface`).
- **Whole-model serialization** via `toJson()` dumped every attribute (found pre-landing in 2.1.0, closed in `modelToString()`).
- **`getResolvedValues()` returns raw values** (found 2026-07-30, still open). The rendered text is sanitised but the result object is not:

```php
$stringValue = $this->valueToString($value);              // sanitised
$translated  = str_replace($token->getFull(), $stringValue, $translated);
$resolvedValues[$token->getRaw()] = $value;               // raw
```

`TranslationResult::getResolvedValues()` is public and `toArray()` includes them, so a consumer that logs the result, returns it over an API, or hands it to a job leaks regardless of the template output.

These are not three bugs. They are one bug three times: policy is applied per data path, so every new path has to be remembered — and it has been forgotten three times.

## 4. Decisions

### 4.1 Enforcement

- **`security.mode` defaults to `enforce`.** `report` and `off` remain available.
- **Blacklist matching gains patterns.** The exact list stays (`password`, `remember_token`, `api_token`, `secret`) and default patterns are added to catch real-world renames: `*_token`, `*_secret`, `*_key`, `*password*`, `*_hash`, `otp`, `pin`, `cvv`. Configurable and disableable. Accepted cost: occasional false positives such as `public_key` or `sort_key`, which are visible in the log and removable by config.
- **Pattern matching is glob-style and case-insensitive**, evaluated with `Str::is()` rather than raw regex. Consumer-supplied config must not be able to inject a catastrophic backtracking pattern, and glob syntax is what a config file reader expects.
- **A blacklist can always be evaded by renaming.** Patterns raise the floor; they do not make this a complete defence. That limitation is stated in the README rather than implied away.

### 4.2 Container serialization

A mustache that resolves to a whole `Model`, `array`, `Collection` or `Arrayable` bypasses every per-path check and dumps whatever is inside. It is also rarely intentional — usually an incomplete path (`{{User.department}}` where `{{User.department.name}}` was meant).

- **Containers are blocked by default**, returning empty plus a log entry.
- **Two escapes**, because they cover different worlds:
  - `allow_container_serialization` (bool, default `false`) — covers plain arrays and collections, which cannot implement anything. The reference consumer flattens models to arrays before calling the resolver, so an interface-only escape would leave it with no way out.
  - `SafeForTemplateSerialization` — an interface a class implements to declare itself safe without opening the global flag.
- When a container is authorised, blacklisted keys are still filtered recursively, and `max_depth` applies to the serialised content.

### 4.3 Model whitelist

- **`allowed_models` requires fully-qualified class names.** Accepting `class_basename` means `['User']` authorises any class in the world whose basename is `User` — that is not a whitelist. The package's own tests used short names, pushing consumers toward the weak mode.
- **An empty list still allows everything.** Requiring a model whitelist from every consumer would make the package unusable on upgrade, and the real defence now always runs: blacklist, patterns, container blocking. The model whitelist is opt-in hardening, not the primary barrier.
- **`ModelNotAllowedException` keeps throwing.** It is the only path that breaks rather than degrades, and that is deliberate: it fires only when the consumer populated `allowed_models`, i.e. explicitly asked for failure.

### 4.4 Removals

- **`allowed_tables` is removed from config.** It is not unimplemented — there is nothing to restrict. `TableResolver` performs no database access; it navigates data the consumer already loaded into context. A table whitelist over a resolver that never reaches a table would be exactly the theatre AID-632 exposed.
- **`ConditionRegistry::getInstance()` / `resetInstance()` are removed.** Finding 8 described cross-request contamination under Octane, but `src/` never invokes them — only their own tests do. This is public static API with no internal consumer: it does not need per-request isolation, it needs deleting. Callers instantiate the registry or resolve it from the container, which does cycle correctly under Octane.

## 5. Architecture — two barriers

### Barrier 1: access (exists, refined)

The per-path check in the accessors. Its value is acting *before* the data is touched: it prevents the lazy relation query, which is what makes `max_depth` meaningful. Kept as is, with `isAttributeBlacklisted()` extended to patterns.

### Barrier 2: output (new)

An `OutputSanitizer` that every resolved value passes through, regardless of which resolver produced it. It cannot be bypassed because it sits downstream of all of them.

```
token → pipeline->resolve()  →  $raw
                                  │
                        OutputSanitizer->sanitize($raw, $token)
                                  │
                                $safe
                                  ├──→ valueToString() → str_replace into template
                                  └──→ $resolvedValues[$token]
```

The decisive change is **where the value forks**. Today sanitisation happens after the fork, which is why `resolvedValues` keeps the raw value. Moving it one step earlier closes that bypass and any future one introduced by a new destination.

### Components

- **`OutputSanitizer`** (new) — scalars pass through; containers are blocked unless escaped, and filtered recursively when authorised. Reports through the validator.
- **`SecurityValidator`** (exists) — gains patterns and strict FQCN matching.
- **`SafeForTemplateSerialization`** (new contract) — per-class opt-in for whole serialization.
- **`MustacheResolver`** loses `modelToString()`, `reportContainerViolations()`, `findBlacklistedKeys()` and `stripBlacklistedAttributes()` to the sanitizer — roughly 130 lines that stop mixing translation with policy.

### Mode awareness

The sanitizer honours all three modes, exactly as barrier 1 does: `off` passes everything through untouched, `report` logs what would be blocked or filtered and returns the value unchanged, `enforce` acts. This is what keeps `report` usable as the pre-upgrade measuring tool described in section 8.

### Error handling

Nothing throws that did not throw before. A blocked value degrades to an empty string plus a log entry, which is the verified behaviour that makes enforcement safe to adopt. The single exception is `ModelNotAllowedException`, unchanged from 2.x and deliberate per section 4.3. Report deduplication stays scoped to the request/job cycle, as built in 2.1.0.

## 6. Configuration

| Key | v2.1.0 | v3.0.0 |
|---|---|---|
| `security.mode` | `report` | `enforce` |
| `security.blacklisted_attributes` | 4 exact names | unchanged |
| `security.blacklisted_patterns` | — | new, with defaults |
| `security.allow_container_serialization` | — | new, `false` |
| `security.allowed_models` | FQCN or basename | FQCN only |
| `security.allowed_tables` | present, inert | removed |
| `security.max_depth` | applied to paths | also applied to serialised content |

## 7. What 2.x keeps

These cannot be fixed in 2.x, because fixing them *is* the breaking change. The README and the upgrade guide name them explicitly, so nobody concludes that staying on 2.x is covered:

- **Arrays and collections are never filtered, not even in `enforce`.** Fixed as contract by `it('leaves containers unfiltered in enforce mode (deferred to v3)')`. Reachable by any template, so this is the one that matters. Whole *models* are already filtered in 2.1.0 by `modelToString()` — see `README.md:174`.
- **`getResolvedValues()` returns raw values.** Only leaks if the consumer logs, serialises or forwards the result object — but that is a common thing to do.
- **The model whitelist is evadable by basename.** Minor: only affects consumers who populated `allowed_models`, and requires an attacker able to introduce a class with a colliding basename.
- **`ConditionRegistry` keeps its static singleton.** Minor: `src/` never invokes it, so only consumers calling it directly under Octane are exposed.

**Support policy:** 2.x receives patches for *new* vulnerabilities and Laravel compatibility for 6 months after the v3.0.0 tag. The holes above are permanent on that line.

## 8. Delivery

### Documentation

- **`UPGRADE-3.md` at the repository root**, not under `docs/` (`export-ignore`). The name matters: `/UPGRADING.md` is listed as `export-ignore` in the skeleton-inherited `.gitattributes`, so that particular name would silently ship nothing. That dead line is also removed, since the file does not even exist.
- The guide carries a `removed → replacement` table and, critically, **a way to measure impact before upgrading**: run 2.1.x in `report` mode and read the `mustache-resolver:` warnings. That is executable from 2.x, unlike an instruction that depends on the version being installed.
- **README** gets a version-policy banner, and its security section describes *behaviour* — what is blocked out of the box and what must be opened on purpose — instead of describing config keys.
- **CHANGELOG**: the BREAKING notice goes inside the `## [3.0.0]` section.

### Packaging hygiene

The current dist ships 8 files, 4 of which are internal noise: `.gitlab-ci.yml`, `CLAUDE.md`, `infection.json5`, `renovate.json`. All four get `export-ignore`. `CLAUDE.md` is the worst offender — in-house agent instructions travelling to every consumer.

### Branching

`main` stays on 2.x throughout development; v3 lives on `3.x`; on tagging `v3.0.0` it becomes `main` and a `2.x` maintenance branch is cut.

## 9. Testing

Beyond per-feature coverage, two guardrails aimed at the bug class rather than its instances:

- **Hostile resolver.** Register a custom resolver that navigates on its own and returns a whole model, then assert the sanitizer intercepts it anyway. This is the proof that barrier 2 covers code that does not exist yet — the failure mode that produced all three bypasses.
- **The three bypasses as regression tests**, each seen red against 2.1.0 before being accepted, `getResolvedValues()` included since it has no policy coverage today.

Feature coverage: patterns (including a documented false positive), containers blocked and both escapes, FQCN rejection of short names, `max_depth` over serialised content, and an upgrade-path test seeding a 2.x configuration and asserting the 3.x behaviour.

## 10. Out of scope (see 11.4 for a later addition)

- Implementing table-level access control. It requires a resolver that actually queries the database, which does not exist; that is a feature, not a fix.
- Turning the blacklist into a whitelist of exposable fields. It is the only complete defence but would render the package unusable until each consumer enumerated its models.
- Any change to `strict` / `keep_unresolved` semantics. Verified as orthogonal: security blocking never reaches the strict-mode failure path.

## 11. Amendment — 2026-07-31

An adversarial review of sections 1-10 raised four blockers, all verified against the code and all upheld. This section records the resolutions. Where it conflicts with an earlier section, this section wins.

One factual error was corrected inline rather than here: section 7 claimed containers are never filtered in v2, which contradicted section 3. Whole *models* are filtered in 2.1.0; arrays and collections are not.

### 11.1 Existing consumers never reach `enforce`

Changing the package default does not move anyone who published the config. Verified in the framework:

```php
$config->set($key, array_merge(require $path, $config->get($key, [])));
```

`array_merge` is shallow and `security` is a top-level key, so a consumer's published block **replaces the package block entirely**. On upgrade they keep `mode: report` *and* the new v3 keys simply do not exist for them. And under `config:cache` the merge never runs at all.

So "v3 defaults to enforce" is true only for fresh installs. Stating it unqualified would repeat the exact defect AID-632 exposed: trusting a default that an intermediate layer masks.

**Resolution — warn and fill absent keys:**

- A key that is **absent** takes the v3 default. A key present as `[]` means the consumer deliberately disabled it. These must be distinguished with `array_key_exists()`, never with `??`, which would silently override a deliberate choice.
- **`mode` is never overridden.** There is no way to tell an inherited `report` from a chosen one.
- The startup warning must state, unambiguously: which keys were absent and what defaults now apply; the **effective mode**; that under `report` the new protections **only report, they do not block**; the exact edit required; and a link to `UPGRADE-3.md`.
- The warning must not assert *which file* the configuration came from — under cached config that cannot be verified.

**This is the point most easily overstated:** with `mode: report` preserved, patterns only report and containers are not blocked. The v3 defaults are loaded, not in force. README and CHANGELOG must say exactly that — fresh installs get `enforce`; configurations published under v2 keep their mode explicitly.

**Why not `replaceConfigRecursivelyFrom()`.** Laravel ships one, and it looks like the obvious fix since `array_replace_recursive` does fill in absent keys. It is rejected because it corrupts the keys that *are* present: recursive replacement merges lists **by numeric index**, so a consumer declaring `blacklisted_attributes => ['my_field']` would end up with `['my_field', 'remember_token', 'api_token', 'secret']` — their entry overwriting position 0 and the package's tail leaking through. An explicit `[]` would likewise disable nothing, since an empty array replaces no positions. Key presence must therefore be inspected deliberately with `array_key_exists()`, never delegated to a recursive merge.

### 11.2 Standalone usage

The public standalone example builds `MustacheResolver` with no validator (`README.md:57`) and the constructor accepts one as nullable (`MustacheResolver.php:27`). So "enforce by default" currently describes the Laravel integration only, not the framework-agnostic package the README advertises.

**Proposal:** `null` stops meaning "no policy" and starts meaning "the default policy". A resolver built without a validator gets one constructed with the v3 defaults — `enforce`, exact blacklist, patterns, containers blocked. Opting out requires `mode: off` explicitly. The signature does not change; the behaviour does, which is what a major is for.

The default standalone validator carries no reporter (the Laravel one injects `Log::warning`), so it blocks silently unless the caller supplies one. The README must show both paths: Laravel via the provider, standalone via explicit construction.

Approved by the owner on 2026-07-31.

### 11.3 Scalar bypass and the trust boundary

Section 5 claimed the sanitizer cannot be bypassed while also stating that scalars pass through. Both cannot hold: a resolver that navigates on its own and returns the password *string* clears the accessor barrier and sails through the output barrier, because a `string` carries no mark of origin.

**Resolution — validate the token path centrally, and declare the limit:**

- The sanitizer re-validates the **token path** before the value reaches either destination, not just the value's type. This covers the package's own resolvers navigating on their own — the failure that actually happened twice.
- **Consumer-registered resolvers are trusted code**, declared as such in the README and outside the threat model. A hostile custom resolver returning a secret under an innocuous token cannot be detected by inspecting token and value alone — and it would not need this package to exfiltrate anything.

Required tests: an internal hostile resolver returning a blacklisted scalar; blocking in **both** the rendered text and `getResolvedValues()`; `report` observing without modifying and `off` passing through untouched; no duplicate warnings between accessor and sanitizer; paths with relations, indices and wildcards.

### 11.4 `allowed_models` → `allowed_root_models`

`validateModel()` runs only in the `EloquentAccessor` constructor, so models nested inside an array are never checked. AID-733 asks for recursive coverage; that is **withdrawn**, with reasons.

Covering it properly would mean replacing `data_get()` with an in-house navigation engine handling arrays, objects, collections, indices and wildcards — too large a regression surface for an opt-in control. Covering it *partially* (validating only on serialization) is worse than not covering it: it turns the whitelist into one that applies sometimes, which cannot be explained honestly.

**Resolution:**

- The key is **renamed to `allowed_root_models`**. Since this is a major, the honest name ships with the behaviour, and no consumer can read a guarantee into it that does not exist.
- It validates the **root model only**, documented as root-context hardening rather than a control over every object traversed.
- When the list is non-empty but the root datum is an array, a warning states explicitly that a class whitelist cannot be applied.
- Nested model *serialization* remains covered by the container policy; nested *scalar* access remains covered by blacklist and patterns — neither is presented as equivalent to a class whitelist.
- **Explicit trade-off, stated in the README:** a non-blacklisted attribute of a nested model resolves even when its class is absent from `allowed_root_models`.
- `UPGRADE-3.md` records `allowed_models` → `allowed_root_models`.

### 11.5 Serialization contract

Public API, so it is fixed here and documented as a break:

- **Blocked container:** empty string in the rendered text, `null` in `getResolvedValues()`. The key is present — its absence would be indistinguishable from a token that never resolved.
- **Authorised container:** `getResolvedValues()` receives the **sanitised** value, so a filtered model arrives as a filtered array, not as a `Model`. The rendered text keeps today's string behaviour, `escapeWhenCastingToString()` included.
- **`max_depth`** counts from the context root: token depth plus depth inside the serialised content. The limit measures how deep data is exposed, wherever the depth comes from.
- **Exceeding depth prunes the offending branch**, not the whole container, and reports.
- **Types:** enums, `DateTimeInterface` (Carbon and friends) and classes marked `SafeForTemplateSerialization` resolve as scalars. `Arrayable`, `JsonSerializable` and `Traversable` are containers, blocked by default — and so is every other object, `Stringable` included. *(Amended 2026-08-03, pre-tag gate fail-open #2: the original bullet read "`Stringable` and enums resolve as scalars", which let any object serialise itself past the container policy through an opaque `__toString()` — atomicity is granted by interface, never by the mere ability to become a string.)*
- **Cycles** are detected with `SplObjectStorage`; a revisited node is cut and reported.
- **`toArray()` throwing, or returning another object,** is treated as a blocked container and reported. Never as an empty success.

### 11.6 Which tokens carry a security path

Attribute rules must not leak onto tokens that are not attribute access. Function, variable, math and dynamic tokens do not go through blacklist or `max_depth` checks — doing so would manufacture false positives on names the consumer controls entirely. The spec must enumerate, per `TokenType`, whether it carries a security path, and the enumeration must be covered by a test so a new token type cannot default into either regime by accident.

### 11.7 v2 support policy — precision

The decision stands as taken: 6 months of patches for **new** vulnerabilities and Laravel compatibility. What was missing:

- **Absolute EOL date**, computed from the `v3.0.0` tag and written as a date in README and `UPGRADE-3.md` — not "six months after".
- **Minimum severity** that triggers a 2.x patch.
- **What "new vulnerability" means** while known ones stay open: one not listed in section 7. Those four are permanent on the 2.x line and closing them is what makes v3.

### 11.8 Consumer communication

Documentation artefacts are not a process. The release must record: consumer inventory with current constraints; owner, channel and date of advance notice; publication and EOL announcements; acknowledgement from known consumers; and README, CHANGELOG, guide and GitLab/Packagist release cross-linked to each other.

### 11.9 Ticket reconciliation — required before implementation

AID-733 and this spec diverge. The ticket is the board's source of truth and must be amended before it leaves Backlog:

- Recursive `allowed_models` coverage: **withdrawn**, with the reasoning in 11.4, plus the rename.
- Token-count and template-length limits: **the ticket has them, this spec omitted them.** They belong in scope — a template with many relation paths amplifies lazy queries without a ceiling, which is the other half of finding 4.
- `ConditionRegistry`: the ticket says isolate, the decision is to **remove**.
- The `fromModel` fail-open the ticket already raises is the same issue as 11.2.
- The ticket carries **no link to this spec**.

### 11.10 `UPGRADE-3.md` contents

Minimum, beyond the generic criteria in section 8: Composer constraints for staying on 2.x or moving to 3.x; published configuration and config caching; every break with before/after; the type and value changes in `getResolvedValues()`; the `allowed_models` → `allowed_root_models` migration; `ConditionRegistry` replacements; containers with both escapes and the pattern false positives; the observation procedure on 2.x, post-upgrade verification and rollback; known risks and the absolute 2.x EOL date.

The README needs the v3/v2 matrix, support status, secure installation for both Laravel and standalone, default behaviour, and a prominent link to the guide.
