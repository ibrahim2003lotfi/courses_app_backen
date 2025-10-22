# Staging Environment Setup

## Prerequisites

- PHP 8.2+
- PostgreSQL 12+
- Redis (optional, will use file cache if not available)
- Composer

## Quick Setup

### 1. Setup Staging Environment
```powershell
# Windows PowerShell
.\infra\staging\scripts\setup-staging.ps1

# This will:
# - Create staging database
# - Copy environment configuration
# - Generate application key
# - Run migrations
# - Seed test data