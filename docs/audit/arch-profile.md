# Architecture Profile
Generated: 2026-11-24

## Detected Patterns

### Plugin / Extension — Medium confidence
Evidence:
- `src/PHPDocsMD/TableGenerator.php` is the one extension contract: every
  method the command calls on the output stage (`openTable`, `addFunc`,
  `getTable`, `appendExamplesToEndOfTable`, `doDeclareAbstraction`,
  static `formatExampleComment`) is declared there, together with the markup
  convention the command's class-name linking relies on.
- `PHPDocsMDCommand::buildTableGenerator()` resolves `--tableGenerator` either
  through a slug registry (`['default' => MDTableGenerator::class]`) or as a
  fully qualified class name checked with `is_a(..., TableGenerator::class)`.
- `MDTableGenerator` is the only shipped extension; the core imports it only
  in that registry.

### Pipe and Filter — Low confidence
Evidence:
- The run is a fixed sequence of stages: the command finds classes →
  `Reflections\Reflector` builds entities → `DocInfoExtractor` and
  `TypeResolver` turn docblocks into resolved types → the `TableGenerator`
  renders rows → the command links class names across the assembled document.
- No `pipeline/`, `filters/` or stage abstraction names it; the stages are
  method calls inside `PHPDocsMDCommand::execute()` and `Reflector`.

### Layered / N-Tier — Low confidence
Evidence:
- `Console/` (entry point), `Reflections/` (analysis) and `Entities/` (model)
  read as tiers. `Entities` depends only on `Utils`; the reflection stage —
  `Reflector`, `ClassEntityFactory`, `FunctionFinder` — lives in
  `Reflections/`, where the `FunctionFinder` ↔ `Reflector` mutual recursion
  that resolves `{@inheritDoc}` stays internal.
- `DocInfoExtractor` sits in the root namespace and writes onto
  `Entities\CodeEntity`; the root namespace has no layer name.

## Detected Combination
Custom hybrid: Plugin / Extension around a staged (pipe-and-filter shaped)
generator. No canonical combination applies.

## Inferred Structural Rules
- The core depends on output generators only through `TableGenerator`; the
  slug registry is the one place a concrete generator is named.
- Everything the core assumes about a generator's output is part of the
  extension contract and must be stated on `TableGenerator`, because a
  third-party generator is written against that interface alone.

## Ambiguities & Contradictions
- With Layered at Low confidence no layering rule is inferred; the root
  namespace mixes extraction (`DocInfoExtractor`, `TypeResolver`) with shared
  helpers (`Utils`, `UseInspector`).
