# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

AWS infrastructure and configuration repository for the **ConectaTech.co** project. Currently documentation-only; no application code or IaC has been added yet.

## AWS Environment

- **Account ID**: 648232846223 (cross-account access via role assumption)
- **AWS CLI profile**: `im` (configured in `~/.aws/config` using `AdministradorExterno` role)
- **Region**: us-east-1
- **EC2 key pair**: `~/.ssh/ClaveIM.pem`

### Verify AWS Access

```bash
aws sts get-caller-identity --profile im
```

All AWS CLI commands in this project should use `--profile im`.

## Repository Structure

```
docs/   # AWS account documentation and reference notes
```
