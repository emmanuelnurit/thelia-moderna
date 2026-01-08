# Critical: Remove exposed GitHub token from .env file

## Overview

The file `.auto-claude/.env` contains a hardcoded GitHub token (`GITHUB_TOKEN=[REDACTED_TOKEN]`). This token is committed to version control and exposed in the codebase, allowing anyone with access to the repository to impersonate the token owner and access/modify GitHub resources.

## Rationale

Exposed API tokens are a critical security vulnerability. Attackers can use the token to access private repositories, create/delete repositories, modify code, access organization data, or perform actions as the token owner. GitHub tokens should never be committed to version control.

---
*This spec was created from ideation and is pending detailed specification.*
