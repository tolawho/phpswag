---
name: safe_framework
description: Guide for applying Essential SAFe practices (PI Planning, Iterations, Artifacts).
---

# SAFE Framework Skill

This skill guides you in applying Essential SAFe practices within the `phpswag` project.

## 1. Core Concepts
-   **ART (Agile Release Train)**: A virtual organization of 5-12 teams (50-125+ people) that plans, commits, and executes together.
-   **PI (Program Increment)**: A timebox (typically 8-12 weeks) during which an ART delivers code.
-   **Iteration**: A standard 2-week agile sprint.

## 2. Planning & Execution

### 2.1 PI Planning (The Heart of SAFe)
When participating in or simulating PI Planning:
1.  **Context**: Understand the *Vision* and *Roadmap* first.
2.  **Breakdown**: Decompose **Features** into **User Stories**.
3.  **Dependencies**: Identify dependencies with other teams (or hypothetical teams if working alone).
4.  **Risks**: ROAM your risks:
    -   **R**esolved: Addressed now.
    -   **O**wned: assigned to someone.
    -   **A**ccepted: Live with it.
    -   **M**itigated: Plan B.
5.  **Output**: A set of committed PI Objectives with business value.

### 2.2 Iteration Execution
during an iteration (sprint):
-   **Planning**: Commit to specific stories from the PI backlog.
-   **Daily**: Standup to track progress toward Iteration Goals.
-   **Review**: Demonstrate working software (System Demo).
-   **Retro**: Inspect and Adapt.

## 3. Artifact Guidelines

### 3.1 Features (Program Level)
-   **Structure**: Benefit hypothesis + Acceptance Criteria.
-   **Estimation**: WSJF (Weighted Shortest Job First).
    -   `WSJF = Cost of Delay / Job Size`
    -   *Cost of Delay* = User-Business Value + Time Criticality + RR | OE (Risk Reduction | Opportunity Enablement).

### 3.2 Stories (Team Level)
-   **Format**: "As a [Role], I want [Activity], so that [Benefit]".
-   **Acceptance Criteria**: Clear Pass/Fail conditions.
-   **Estimation**: Story Points (Fibonacci).

### 3.3 Enablers
-   Work that supports future business functionality (Architecture, Infrastructure, Compliance).
-   Treat them like Features/Stories but with technical "Business Value".

## 4. Hierarchy Check
-   **Portfolio**: Epics (Strategic Themes).
-   **Program (ART)**: Features (fit in a PI).
-   **Team**: Stories (fit in an Iteration).

## 5. Principles to Remember
-   **#1 Take an economic view**: Optimize for shortest sustainable lead time.
-   **#6 Visualize and limit WIP**: Stop starting, start finishing.
-   **#9 Decentralize decision-making**: Don't wait for approval on tactical decisions.
