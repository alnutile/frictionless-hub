# Frictionless Hub — Platform Spec (v0.1)

A web platform for running a developer-experience (DevEx) improvement program end to end:
discover friction, pick projects, gather data, prioritize, communicate, and report impact.
The structure follows the process in *Frictionless* (Forsgren & Noda) and its companion
workbook.

## Content policy (read first)

**No book or workbook content ships in this repo.** That means no copied text, templates,
example tables, survey questions, rubric criteria, or email copy.

- The code implements **generic engines**: rating grids, matrices, scoring rubrics, a
  survey builder, message templates with merge fields, and calculators.
- Everything a reader would recognize from the book (question wording, criteria lists, the
  examples) is **data an admin enters** into their own workspace. It never goes into a
  migration, a seed file, or a fixture.
- Seeds and test fixtures use neutral placeholder content ("Criterion A", "Sample question 1").
- Publicly published frameworks can be named and modeled: RICE, SPACE, DORA metrics, Likert
  scales, and story mapping.

## Roles

| Role | Can do |
|---|---|
| **Admin** | Manage the workspace, users, and initiatives. Configure instruments (rubrics, scales, question banks). See all data. |
| **Facilitator** | Run an initiative: interviews, surveys, scoring, comms. Sees identified data only for their own initiatives. |
| **Stakeholder (viewer)** | Read-only dashboards and reports, aggregated and anonymized. |
| **Respondent** | No account. Uses a tokenized survey or rating link. Responses are stored apart from any contact info. |

## Core concept: Initiative

Everything is scoped to an **Initiative**, which is one DevEx program for an org, business
unit, or team. An initiative moves through phases that mirror the book's steps. A phase is a
nav section, not a hard gate.

```
Discover → Quick wins → Data → Prioritize → Sell → (Strategy) → Implement & Report
```

## Modules by phase

### 1. Discover
- **Workflow map.** Configurable stages (default stage names are generic SDLC terms). Each
  stage lists its tools and technologies, has notes, and carries an "AI-assisted" flag.
- **Friction log.** Fields: stage, description, frequency (enum), pain (1–5), affected
  segments, and DevEx dimension tags (configurable tag set). Rendered as a heatmap by
  stage × dimension.
- **Story map board.** A 2-D board with activities across the top and tasks and stories
  below. Layers (friction, tools, gaps, teams) can be toggled on and off.
- **Workflow quick rating.** A reusable **rating grid instrument**: groups of items, each
  scored 1–5, with configurable end labels. The admin defines the items. Respondents can
  fill it in through a link.
- **Segments.** Demographic *categories* (experience, tenure, area, …), each with mutually
  exclusive *values*. The platform enforces this: one value per category per person.
- **Listening tour tracker.** Participants tagged by segment. A coverage matrix shows work
  area × segment values, and a **consistency check** confirms that each fully covering
  category sums to the area's total. It also holds a pre-interview checklist (admin-defined
  items) and invite templates.
- **Interview capture.** A structured note form: participant, role, team, tools, pain
  points, bright spots, to-dos with owners, and codes or themes. Optional transcript upload.
- **Theme synthesis.** Themes are linked to coded interview notes. Each theme stores its
  mention count (computed), affected roles and stacks, impact, difficulty, supporting
  quotes, and candidate solutions.
- **Stakeholder matrix.** For each group: representatives, roles, interest, influence, and
  understanding (H/M/L), stance (advocate, neutral, or resistant), concerns, channels, and
  engagement plan. Plotted as an interest × influence chart.

### 2. Quick wins
- **Candidate projects**, which can be promoted from themes.
- **Quick RICE** scoring on H/M/L for each component, with a sortable comparison table and
  a highlight for "high reach + impact, low effort".

### 3. Data (the main data-gathering module)
- **Metrics catalog.** Each metric records whether it is currently measured, data quality,
  accessibility, SPACE dimension tags, and whether it is system or self-report data. The
  catalog warns when chosen metrics cover fewer than three SPACE dimensions.
- **Survey builder.** Multi-page surveys with question types: single choice, multi choice
  (with a max-select limit), matrix, scale, and open text. Includes piping (a later
  question can reference earlier selections), optional answers, N/A choices, and a
  progress indicator. Scales come from a **scale library** that the admin manages.
- **Distribution.** Audience lists built from segments, tokenized links, invite, reminder,
  and results templates, a live response-rate dashboard, and close dates.
- **Anonymity by design.** Responses and contact opt-ins go in separate tables with no join
  key exposed to facilitators. Segment breakdowns hide cells below a minimum group size.
- **Data mapping and coverage.** Responses and metrics broken down by segment, with a
  report on whether there is enough data (thresholds the admin can configure).
- **System metrics import.** CSV import first. Connectors (GitHub, CI) come later.

### 4. Prioritize
- **Rubric engine.** Sections hold criteria, scored Yes/Maybe/No. Each section has a
  weight, and the engine computes a weighted total and priority band (High, Medium, or Low;
  thresholds configurable). The admin authors the sections and criteria.
- **Project one-pager.** Fields: challenge, score, business value, timeline, action, and
  constraints.

### 5. Sell your strategy
- **Audience profiles.** Each covers a stakeholder group's needs, motivations, channels,
  and background.
- **Comms timeline.** Phases (for example preparation, announcement, reinforcement), each
  with messages, channel, owner, and date. Shown as a calendar.
- **Message templates.** Merge fields such as `{{first_name}}`, `{{deadline}}`,
  `{{survey_link}}`, and `{{response_rate}}`. Templates can be exported or copied.
  Email sending comes later.

### 7. Implement & report
- **Value calculator.** Hours saved × number of developers × loaded cost gives an annual
  value. Compares that value with implementation cost to show ROI for each initiative.
- **Value roadmap.** Initiatives on a timeline, each showing its expected value type.
- **Attribution and conflict notes.** Annotate a metric when it moved for reasons other
  than the initiative.
- **Org model assessment.** Compares candidate structures against configurable
  considerations.
- **Resistance log.** Records the source, type, response strategy, and status.
- **Reports.** Shareable, anonymized views for stakeholders.

## Data model (first pass)

```
workspaces, users, memberships(role)
initiatives(workspace_id, name, scope, phase)
segment_categories, segment_values, people(initiative_id), person_segments
workflow_stages, stage_tools, friction_points, friction_dimensions
story_maps, story_map_items(layer, col, row)
instruments(kind: rating_grid|rubric|checklist), instrument_sections, instrument_items
instrument_runs, instrument_responses
interviews, interview_notes, codes, interview_codes, themes, theme_links
stakeholder_groups, stakeholder_assessments
projects, rice_scores, rubric_scores, project_briefs
metrics, metric_observations(segment_value_id?, period, value)
scales, scale_options
surveys, survey_pages, survey_questions, survey_invites(token)
survey_responses, survey_answers          -- no person_id
survey_contacts(survey_id, email, opt_ins) -- stored separately
message_templates, comms_events
value_models, roadmap_items, annotations, resistance_items
audit_log
```

## Out of scope for v0.1
Email sending, SSO, live GitHub/CI connectors, and AI summarization of interviews. AI
summarization is a good v0.2 candidate.

## Open decisions
1. **Stack.** Options are Laravel + Inertia/React (or Livewire) with Boost MCP, or the
   SupaNet pattern (React/Vite/TS + Supabase with RLS).
2. **Tenancy.** One workspace per deploy, or multi-tenant from day one.
3. **Hosting.** Railway (as in the template), Laravel Cloud, or Forge.
