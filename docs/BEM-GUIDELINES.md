# BEM Naming Guidelines

We name CSS classes using **BEM** — Block, Element, Modifier. The goal is class
names that describe *what a thing is*, are flat (no descendant-selector
nesting), and are safe to grep, move, and delete.

## The three parts

| Part         | Meaning                                  | Syntax            | Example              |
|--------------|------------------------------------------|-------------------|----------------------|
| **Block**    | Standalone, reusable component           | `.block`          | `.scan-table`        |
| **Element**  | A part that only exists inside its block | `.block__element` | `.scan-table__row`   |
| **Modifier** | A variant or state of a block/element    | `.block--mod`     | `.scan-table--empty` |

- Block ↔ Element separator: `__` (double underscore)
- Block/Element ↔ Modifier separator: `--` (double dash)
- Names within a part: `kebab-case` (`.status-badge`, not `.statusBadge`)

## Rules

1. **Style with a single class, not nested selectors.**
   - ✅ `.scan-table__row { ... }`
   - ❌ `.scan-table tr { ... }` / `.scan-table .row { ... }`
   - The class is the contract; descendant selectors leak and break on refactor.

2. **Elements belong to exactly one block.** `__row` only makes sense inside
   `.scan-table`. If a part needs to live on its own, it's a block, not an element.

3. **Don't chain elements.** Write `.scan-table__cell`, not
   `.scan-table__row__cell`. BEM is two levels deep by name; DOM nesting can go
   as deep as it likes.

4. **Modifiers never stand alone.** Always pair a modifier with its base class
   in the HTML: `class="badge badge--running"`. Style the modifier additively —
   it only overrides what changes.

5. **Modifiers carry state, too.** Active/disabled/loading are modifiers:
   `.tab--active`, `.button--disabled`. Prefer these over `is-` prefixes for
   visual state owned by the component.

6. **Blocks don't set their own outside margins/position.** Spacing between
   blocks is the parent layout's job, so a block stays reusable anywhere.

## Applying it here

The current views (`resources/views/`) use flat, overloaded classes. Migration
targets:

| Now                       | BEM                                  |
|---------------------------|--------------------------------------|
| `.badge.method`           | `.badge badge--method`               |
| `.badge.running`          | `.badge badge--running`              |
| `button.secondary`        | `.button button--secondary`         |
| `.banner` / `.error`      | `.notice` + `.notice--warning` / `.notice--error` |
| `.toolbar`                | `.toolbar` (block) + `.toolbar__item` |

### Example

```html
<!-- before -->
<span class="badge running">running</span>
<button class="secondary">Re-scan</button>

<!-- after -->
<span class="status-badge status-badge--running">running</span>
<button class="button button--secondary">Re-scan</button>
```

```css
.status-badge { display: inline-block; padding: .1rem .5rem; border-radius: 999px; }
.status-badge--running { background: #fff4d6; color: #8a6d00; }

.button { /* base */ }
.button--secondary { background: #fff; color: #2952cc; }
```

## Quick checklist for review

- [ ] Every styled element has its own class (no tag/descendant selectors).
- [ ] Element names read `block__element`, never chained.
- [ ] Modifiers appear alongside their base class in the markup.
- [ ] Blocks have no external margin/positioning.
- [ ] Names are kebab-case and describe purpose, not appearance
      (`--danger`, not `--red`).
