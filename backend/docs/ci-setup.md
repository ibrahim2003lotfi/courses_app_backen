# CI/CD Setup Instructions

## 1. Repository Setup

### Branch Protection Rules
Add branch protection rules for `main` and `develop`:
- Require status checks to pass before merging
- Require branches to be up to date before merging
- Include administrators in restrictions

### Required Status Checks
- `Tests & Analysis`
- `Static Analysis`
- `Security Scan`
- `PR Validation`

## 2. Secrets Configuration

Add these secrets in GitHub Settings > Secrets:

### For Staging Deployment
- `STAGING_HOST`: Staging server hostname
- `STAGING_USER`: SSH username
- `STAGING_KEY`: Private SSH key
- `STAGING_PATH`: Deployment path

### For Production Deployment
- `PRODUCTION_HOST`: Production server hostname
- `PRODUCTION_USER`: SSH username  
- `PRODUCTION_KEY`: Private SSH key
- `PRODUCTION_PATH`: Deployment path

### Optional Integrations
- `CODECOV_TOKEN`: For code coverage reports
- `SLACK_WEBHOOK`: For deployment notifications

## 3. Environment Configuration

### GitHub Environments
Create environments in Settings > Environments:
- `staging`: No deployment protection
- `production`: Require reviewers, deployment branches

## 4. Auto-merge Labels
Create labels for PR automation:
- `auto-merge`: Enable auto-merge after approval
- `breaking-change`: Mark breaking changes
- `security`: Security-related changes

## 5. Monitoring

The CI pipeline includes:
- ✅ Automated testing
- ✅ Static analysis (PHPStan, Psalm)
- ✅ Security scanning
- ✅ Code coverage reports
- ✅ Build artifacts
- ✅ Deployment automation