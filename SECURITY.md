# Security Policy

## Reporting a Vulnerability

If you believe you have discovered a security vulnerability in ImportPilot, please **do not** create a public GitHub issue. Public disclosure of unpatched security vulnerabilities puts all users at risk.

### How to Report

Use GitHub's private vulnerability reporting feature:

1. Navigate to the **Security** tab of this repository.
2. Click **"Report a vulnerability"**.
3. Provide a clear description of the issue, steps to reproduce, and any relevant environment details.

If GitHub's private reporting is unavailable for any reason, please contact the maintainers through the contact information listed in the GitHub profile associated with this repository.

### What to Include

A useful vulnerability report includes:

- A clear description of the vulnerability type (e.g., injection, authentication bypass, information disclosure).
- Step-by-step reproduction instructions.
- The affected component or file path, if known.
- The potential impact in your assessment.
- Any proof-of-concept code or screenshots, if applicable.

### What to Expect

- You will receive an acknowledgement within **a reasonable timeframe** once the project reaches a staffed operational state.
- During the pre-release phase, response timelines are best-effort. We will prioritize confirmed, exploitable vulnerabilities.
- We will work with you to understand and remediate the issue before any public disclosure.

### Scope

In-scope issues include:

- Authentication and authorization bypasses.
- Injection vulnerabilities (SQL, command, template).
- Insecure direct object references exposing tenant data.
- Credentials or secrets accidentally committed to repository history.
- Sensitive data exposure in API responses.
- Server-side request forgery.
- Insecure deserialization.

Out-of-scope during pre-release:

- Denial-of-service attacks against the development environment.
- Social engineering of maintainers.
- Issues in third-party libraries without a working exploit against this application.

### Disclosure Policy

We follow a **responsible disclosure** model. We ask that you:

- Allow adequate time for remediation before any public disclosure.
- Do not exploit the vulnerability beyond what is necessary to demonstrate it.
- Do not access, modify, or destroy data belonging to others.

### No Bug Bounty Program

ImportPilot does not currently operate a formal bug bounty program. We acknowledge security researchers' contributions in release notes when appropriate and with their consent.

---

*This security policy will be updated as the project matures and operational processes are formalized.*
