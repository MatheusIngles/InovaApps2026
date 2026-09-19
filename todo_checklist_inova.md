# 📋 TODO Checklist - Multi-tenant Analytics SaaS

Project requirements and actionable task checklist for building a multi-tenant analytics platform with custom metrics, isolated chat contexts, custom branding, and PHP backend integration.

---

## 🔐 1. Authentication & Multi-Tenancy
- [ ] **Tenant-Specific Login Screen**
  - Implement sub-domain or tenant identifier detection at login (e.g., `company.app.com` or tenant select dropdown).
  - Bind authenticated session strictly to `company_id`.
- [ ] **Context-Isolated Chat System**
  - Ensure prompt histories, memory, and chat sessions are strictly filtered by `company_id`.
  - Prevent cross-tenant data leakage in AI/Chat queries.

---

## 📊 2. Data Input & Spreadsheet Handling
- [ ] **Post-Login Spreadsheet Interface**
  - Automatically direct users to their company's spreadsheet view/upload landing screen upon login.
- [ ] **Dynamic Data Import Engine**
  - Implement file parsing (Excel/CSV).
  - Map uploaded columns dynamically according to company-specific parameters.

---

## 📈 3. Metrics Engine & Priority Configurations
- [ ] **Priority & Parameter Settings Screen**
  - Build UI for setting and ordering metric priorities/weights per company.
  - Store dynamic calculation formulas and parameters in database.
- [ ] **Recalculate & Refactor Base Metric Logic**
  - Review initial core metric calculation formula.
  - Apply company weights and custom parameter priority logic to data inputs.

---

## 🎨 4. Branding & Visual Identity
- [ ] **Dynamic Theme Engine**
  - Switch primary/secondary colors, logos, and fonts automatically based on the logged-in company.
  - Inject tenant design tokens into application styling (CSS Variables / Tailwind config).

---

## 🐘 5. Backend PHP Architecture
- [ ] **Context Management Service**
  - Implement PHP class/service to establish current active company context.
  - Create method/variable to list tenant contexts (e.g., `$companyContext->listContexts()`).
- [ ] **Company Configuration Controller**
  - CRUD operations for metric weights, UI theme settings, and isolated chat settings.

---

## 🧪 6. Refactoring & Testing
- [ ] Review base metric calculation performance and accuracy against edge-case spreadsheets.
- [ ] Verify data isolation security checks on all multi-tenant endpoints.