# Includo

**Includo** is an open-source web accessibility audit tool designed to evaluate websites against **WCAG 2.2 (Level A and AA)** and the requirements of the **European Accessibility Act (EU Directive 2019/882)**.

The project aims to transform accessibility from a purely technical check into a **concrete compliance and cultural practice**, supporting developers, organisations and public bodies.

---

## ✨ Features

- Automated accessibility testing (WCAG 2.2 – Level A & AA)
- Assisted/manual checks for criteria not fully automatable
- Explicit mapping of results to WCAG success criteria
- European Accessibility Act (EAA) compliance checks
- Automatic calculation of conformity status:
  - Compliant
  - Partially compliant
  - Not compliant
- Generation of an **Accessibility Statement** compliant with EU and AgID requirements
- Ready-to-use footer snippet for legal compliance
- Exportable reports (HTML, JSON – PDF optional)

---

## 📚 Standards & Regulations

Includo evaluates accessibility according to:

- **WCAG 2.2**
  - Level A (mandatory)
  - Level AA (mandatory)
- **European Accessibility Act (EU 2019/882)**
- Italian **AgID accessibility guidelines**

WCAG 3.0 is not included, as it is still a draft specification.

---

## 🧩 Accessibility Scope

Includo checks accessibility across three levels:

### 1. Automated Tests
- Images and alternative text
- Color contrast
- Headings and landmarks
- Links and buttons
- ARIA roles and attributes
- HTML validity

### 2. Assisted / Manual Checks
- Focus visibility and focus order
- Target size (WCAG 2.2)
- Drag-and-drop alternatives
- Keyboard operability
- Form error handling

### 3. Legal & Documentation Compliance
- Accessibility Statement generation
- Contact mechanism for accessibility feedback
- Compliance status calculation
- Date and audit traceability

---

## 📄 Accessibility Statement Generation

Includo can automatically generate an **Accessibility Statement** including:

- Website identification
- Regulatory references (WCAG 2.2 & EAA)
- Compliance status
- Non-accessible content list
- Evaluation method
- Contact information for feedback
- Date of publication and last update

### Example footer snippet:
```html
<a href="/accessibilita.html" title="Dichiarazione di accessibilità">
  Dichiarazione di accessibilità
</a>
```

---

## 🚀 Installation

Includo can be installed on any standard web hosting environment.

### Requirements
- Web server (Apache / Nginx)
- PHP 8.0 or higher
- Write permissions for runtime directories (`storage/`, `reports/`, `logs/`, `cache/`)

### Web installer (recommended)
1. Upload the project files to your web space
2. Browse to: `/install/`
3. Follow the guided steps (environment check → configuration → database, if applicable)
4. When finished, **delete the `/install` folder** (recommended) or block access to it

### Manual installation (advanced)
- Create your local config file (`config.local.php` or `includo-config.php`) and keep it **out of version control**
- Make sure runtime folders are writable by the web server user

### Troubleshooting
- **403/500 during install:** check PHP version and missing extensions (cURL/mbstring/DOM/JSON)
- **Cannot write config:** fix filesystem permissions for the project root
- **Reports not generated:** ensure `reports/` (or `storage/reports/`) is writable

## 🛠 Usage

1. Enter the target website URL
2. Run the accessibility audit
3. Review automated and assisted test results
4. Generate the Accessibility Statement
5. Publish the statement and link it in the site footer

---

## 🧪 Limitations & Disclaimer

- Automated tests cannot detect all accessibility issues.
- Manual verification is required for full compliance.
- Generated statements should be reviewed by a qualified professional before publication.
- Includo does not replace legal responsibility of the website owner.

---

## 🗺 Roadmap

- PDF export for Accessibility Statements
- Multilingual statements (IT / EN)
- WCAG criteria coverage dashboard
- Continuous monitoring mode
- CMS integrations (WordPress, static sites)

---

## 👤 Author

**Franco Aquini**  
Web Salad  
https://www.websalad.it

---

## 📄 License

MIT License

You are free to use, modify and distribute this software in compliance with the license terms.


## Accessibility Statement (AgID-ready)

Includo includes an **Accessibility Statement generator** aligned to the Italian **AgID "Allegato 1"** structure.

- Preview (IT): `accessibility_statement.php?session_id=YOUR_SESSION&lang=it`
- Edit/Builder: `accessibility_statement.php?session_id=YOUR_SESSION&lang=it&edit=1`
- Export HTML: `accessibility_statement.php?session_id=YOUR_SESSION&lang=it&export=1`
- Remediation Plan (CSV): `remediation_plan.php?session_id=YOUR_SESSION`

Recommended footer link label: **"Dichiarazione di accessibilità"**.

## Background resume worker (optional)

Includo provides a small CLI worker that can resume paused audit sessions in background. This is useful for long crawls where you prefer to continue processing via cron or a systemd timer rather than via web requests.

Script: `bin/resume_worker.php`

Example crontab (run every 15 minutes):
```cron
*/15 * * * * /usr/bin/php /path/to/includo/bin/resume_worker.php >> /path/to/includo/logs/resume_worker.log 2>&1
```

You can also run it manually from the project root:
```bash
php bin/resume_worker.php
```

Notes:
- Ensure the PHP CLI binary path (`/usr/bin/php`) matches your environment.
- The worker requires database credentials as configured in `config.php` or `config.local.php`.
- Logs are appended to the standard logger file; the cron line above redirects worker output to `logs/resume_worker.log` for convenience.
