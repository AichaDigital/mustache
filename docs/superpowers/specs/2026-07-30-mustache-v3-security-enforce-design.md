# Design — v3.0.0: enforce by default and the end of per-path security

- **Ticket:** AID-733 (continues AID-632, closed)
- **Date:** 2026-07-30
- **Status:** approved, pending implementation plan
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

- **Containers are never filtered, not even in `enforce`.** Fixed as contract by `it('leaves containers unfiltered in enforce mode (deferred to v3)')`. Reachable by any template, so this is the one that matters.
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

## 10. Out of scope

- Implementing table-level access control. It requires a resolver that actually queries the database, which does not exist; that is a feature, not a fix.
- Turning the blacklist into a whitelist of exposable fields. It is the only complete defence but would render the package unusable until each consumer enumerated its models.
- Any change to `strict` / `keep_unresolved` semantics. Verified as orthogonal: security blocking never reaches the strict-mode failure path.
