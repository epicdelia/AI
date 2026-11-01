<?php

namespace App\Domains\Legal;

use App\Services\Prompts;

/**
 * European Union Legal Compliance Suite:
 * 1. General Data Protection Regulation (GDPR - Regulation (EU) 2016/679) Privacy Policy.
 * 2. Commercial Terms & Conditions for Creator Studio & Sponsorship Agreements,
 *    including EU VAT Reverse-Charge (Article 196 Directive 2006/112/EC) and
 *    EU Late Payment Directive (2011/7/EU).
 */
class LegalDocs
{
    public static function privacyPolicy(): array
    {
        $creator = Prompts::name();
        $date = 'November 1, 2026';

        $markdown = <<<MARKDOWN
# Privacy Policy (GDPR Compliant)

**Effective Date:** {$date}  
**Data Controller:** {$creator} Creator Studio ("we", "us", "our")  
**Contact Email:** privacy@techunicorn.dev  

We are committed to full compliance with the **General Data Protection Regulation (EU) 2016/679 ("GDPR")**, the **ePrivacy Directive 2002/58/EC**, and applicable European data protection laws. This Privacy Policy explains transparently how we collect, process, and protect personal data when you interact with our creator studio, client portal, and website.

---

### 1. Lawful Basis for Processing (Article 6 GDPR)

We only collect and process personal data where an explicit lawful basis exists under Article 6 of the GDPR:

- **Article 6(1)(b) — Performance of a Contract:** Processing your name, corporate email, billing address, and project brief to prepare proposals, execute sponsorship agreements, deliver media reviews, and manage client accounts.
- **Article 6(1)(c) — Legal & Regulatory Obligations:** Retaining tax invoices, VAT numbers, and transaction records pursuant to statutory commercial law and **Council Directive 2006/112/EC (Article 196)**.
- **Article 6(1)(f) — Legitimate Interests:** Monitoring system security, preventing brute-force authentication attacks, detecting malware uploads, and logging security audit trails (IP address, browser family) to safeguard client accounts.
- **Article 6(1)(a) — Consent:** Where you voluntarily opt into our newsletter or free developer guides (with revocable 1-click unsubscribe).

---

### 2. Categories of Data We Collect

1. **Client & Account Data:** Legal name, business email, company name, phone number, password hash (bcrypt), two-factor TOTP secrets (encrypted at rest), and client team roles.
2. **Billing & Tax Compliance Data:** Company registration address, EU VAT number, UK VAT, or US EIN, currency, payment status, and Stripe transaction identifiers (we never store raw credit card numbers).
3. **Collaboration & Deliverables Data:** Revision annotations, video timecode feedback, project briefs, Brand Vault guidelines, and uploaded creative assets.
4. **Security & Audit Logs:** IP addresses (stored with subnet hashing for device recognition), browser user agents, timestamps of sign-ins, and cryptographic e-signature fingerprints.

---

### 3. Cookies & Tracking (ePrivacy Directive)

We strictly uphold a **Zero Non-Essential Cookie Policy**:
- **No Third-Party Advertising Cookies:** We do not embed Facebook Pixels, Google Analytics trackers, or cross-site tracking scripts.
- **Strictly Necessary Cookies Only:** Session cookies (`laravel_session`, `XSRF-TOKEN`) required solely for authentication and CSRF security under Article 5(3) of the ePrivacy Directive.

---

### 4. International Data Transfers (Chapter V GDPR)

Where sub-processors outside the European Economic Area (EEA) are engaged (such as US-based AI completion APIs or Stripe payment infrastructure), data transfers are governed by:
- **Standard Contractual Clauses (SCCs)** approved by the European Commission pursuant to Article 46(2)(c) GDPR.
- **Adequacy Decisions** under Article 45 GDPR (such as the EU-U.S. Data Privacy Framework).

---

### 5. Your Data Protection Rights Under GDPR (Articles 15–22)

As a data subject located in the European Union or European Economic Area, you hold the following statutory rights:

1. **Right of Access (Article 15):** Request confirmation and a copy of all personal data held about you.
2. **Right to Rectification (Article 16):** Request immediate correction of inaccurate or incomplete corporate data.
3. **Right to Erasure / "To Be Forgotten" (Article 17):** Request deletion of your client account. Personal identifiers are permanently erased, while statutory financial transaction records are retained strictly as required by commercial tax code.
4. **Right to Restriction of Processing (Article 18):** Request restriction of data processing during dispute resolution.
5. **Right to Data Portability (Article 20):** Download a structured, machine-readable JSON archive of your personal account, messages, and invoices via your dashboard.
6. **Right to Object (Article 21):** Object to processing founded upon legitimate interests.
7. **Right to Lodge a Complaint:** You have the right to lodge a formal complaint with your local EU Data Protection Supervisory Authority (e.g., DPC in Ireland, CNIL in France, BfDI in Germany, AP in the Netherlands, or APD in Belgium).

---

### 6. Technical & Organizational Security Measures (Article 32 GDPR)

We implement state-of-the-art security safeguards:
- **Transport Layer Security:** Enforced HTTPS with HSTS (`Strict-Transport-Security: max-age=31536000; includeSubDomains`).
- **Dynamic Content Security Policy (CSP):** SHA-256 script hashing preventing XSS.
- **Binary Magic-Byte Inspection:** Anti-malware validation blocking disguised executables.
- **Cryptographic Encryption:** API keys and sensitive tokens encrypted at rest via AES-256-CBC.
- **Two-Factor Authentication (TOTP):** Mandatory multi-factor sign-in for studio administrators.

For questions regarding data protection or to exercise your GDPR rights, contact our Data Protection desk at **privacy@techunicorn.dev**.
MARKDOWN;

        return [
            'title' => 'EU Privacy Policy (GDPR Compliant)',
            'effective_date' => $date,
            'contact_email' => 'privacy@techunicorn.dev',
            'markdown' => $markdown,
        ];
    }

    public static function termsAndConditions(): array
    {
        $creator = Prompts::name();
        $date = 'November 1, 2026';

        $markdown = <<<MARKDOWN
# Commercial Terms & Conditions

**Effective Date:** {$date}  
**Service Provider:** {$creator} Creator Studio  
**Governing Jurisdiction:** European Union Member State Commercial Law  
**Contact:** partnerships@techunicorn.dev  

These Commercial Terms & Conditions ("Agreement") govern all sponsorship integrations, consulting services, dedicated media productions, and custom software builds provided by {$creator} ("Creator", "we") to enterprise and brand partners ("Client", "Sponsor").

---

### 1. Scope of Services & Deliverables

1. The exact scope of deliverables, scheduling dates, and placement formats (e.g., YouTube integration, dedicated video, newsletter spotlight, vertical shorts) are specified in the agreed project proposal.
2. All creative drafts and video cuts are submitted for review via the Creator Studio Client Portal.
3. **Review & Revision Window:** Client is entitled to review submitted drafts and provide timestamped revision feedback via the portal within five (5) business days. One (1) consolidation round of reasonable creative revisions is included in standard packages.

---

### 2. Intellectual Property & Commercial Licensing

1. **Creator Retained Rights:** Creator retains full copyright, ownership, and moral rights in all underlying videos, audio recordings, source scripts, and likeness.
2. **Sponsor Distribution License:** Upon receipt of full payment, Creator grants Client a non-exclusive, worldwide license to reference, link to, and share the published placement for promotional purposes.
3. **Paid Advertising & Whitelisting Rights:** Client may not run paid advertisements (Meta Spark Ads, YouTube In-Feed Ads, TikTok Spark Ads) using Creator's content or likeness unless an explicit **Paid Advertising Rights Add-On** (30, 60, or 90 days) is contracted and paid.

---

### 3. Payment Terms, Currencies & EU VAT Reverse-Charge

1. **Accepted Currencies:** Fees are payable in Euros (EUR) or US Dollars (USD) via Stripe Checkout, SEPA Credit Transfer, or International Bank Wire.
2. **EU B2B Reverse-Charge Mechanism:**
   - Under **Article 196 of Council Directive 2006/112/EC**, supplies of services to taxable persons established in other EU Member States are subject to the **Reverse Charge Mechanism**.
   - Client is required to provide a valid, active EU VAT identification number. VAT is to be accounted for by the recipient in their domestic VAT return.
   - For clients outside the EU (e.g., United States), services are invoiced as exported digital creator services outside the domestic scope.
3. **Payment Due Dates:** Payments are due in accordance with the contracted terms (e.g., Due on Acceptance, Net-14, or Net-30).
4. **Statutory Late Interest:** In accordance with the **EU Late Payment Directive (2011/7/EU)**, overdue balances are subject to statutory interest at the European Central Bank reference rate plus 8% per annum, plus statutory recovery compensation costs.

---

### 4. Regulatory Transparency & Ad Disclosures

1. In compliance with the **EU Unfair Commercial Practices Directive (Directive 2005/29/EC)**, the **EU Audiovisual Media Services Directive (AVMSD)**, and US FTC Guidelines:
   - All sponsored placements will feature prominent, transparent commercial declarations (e.g., verbal disclosure within the opening segment and `#ad` / `Sponsored by` disclosures in metadata).
   - Client may not request the removal of statutory sponsorship disclosures.

---

### 5. Exclusivity & Category Non-Compete

1. Standard agreements include a **14-day exclusivity window** (7 days prior and 7 days post publication) during which Creator will not publish dedicated integrations for direct, substantially identical commercial competitor software tools.
2. Extended exclusivity (30 or 60 days) is available under the **Growth Showcase** or **Enterprise Domination** proposal tiers.

---

### 6. Non-Repudiation & Cryptographic Electronic Signatures

1. In accordance with the **EU eIDAS Regulation (Regulation (EU) No 910/2014)** regarding electronic signatures in commercial contracts:
   - Proposals accepted via the Creator Studio deal room record a cryptographically verifiable SHA-256 fingerprint, verified IP address, email, and timestamp.
   - Both parties agree that cryptographic electronic acceptance carries full binding legal enforceability.

---

### 7. Limitation of Liability & Force Majeure

1. Except for willful misconduct or gross negligence, Creator's aggregate liability under any engagement is strictly limited to the total fees paid by Client for the specific affected deliverable.
2. Neither party shall be liable for delays resulting from causes beyond reasonable control, including platform algorithm modifications, API outages, or documented medical emergencies.

---

### 8. Governing Law & Dispute Resolution

This Agreement is governed by and construed in accordance with the commercial laws of the **European Union**. The parties agree to resolve any dispute amicably through informal consultation before initiating formal legal proceedings.
MARKDOWN;

        return [
            'title' => 'Commercial Terms & Conditions',
            'effective_date' => $date,
            'markdown' => $markdown,
        ];
    }

    /**
     * Mandatory European Union Legal Notice (Impressum) pursuant to
     * Section 5 of the Digital Services Act (DDG - Digitale-Dienste-Gesetz),
     * Directive 2000/31/EC (e-Commerce Directive), and EU AI Act (Art. 50).
     */
    public static function impressum(): array
    {
        $creator = Prompts::name();
        $date = 'November 1, 2026';

        $markdown = <<<MARKDOWN
# Legal Notice (Impressum)

Information required pursuant to **§ 5 Digitale-Dienste-Gesetz (DDG)**, **Directive 2000/31/EC (e-Commerce Directive)**, and **Regulation (EU) 2024/1689 (EU AI Act)**.

---

### 1. Service Provider Details

**Operator & Legal Entity:**  
{$creator} Creator Studio  
Sole Proprietorship / Independent Media & Engineering Consultancy  
**Founder & Legal Representative:** Delia Lazarescu  

**Registered Business Inquiries:**  
Email: **legal@techunicorn.dev** / **hello@thetechunicorn.com**  
Website: [https://thetechunicorn.com](https://thetechunicorn.com)  
Client Portal: [https://studio.thetechunicorn.com](https://studio.thetechunicorn.com)  

---

### 2. Value Added Tax (VAT) & Tax Identification

- **VAT Identification Number (USt-IdNr. / VIES):** Registered intra-Community EU VAT identification number on file pursuant to **Article 214 of Council Directive 2006/112/EC**.
- **Cross-Border B2B Supply:** Validated active under European Commission VIES. All cross-border business transactions within the EU are invoiced subject to the **Reverse Charge Mechanism (Article 196 Council Directive 2006/112/EC)**.
- **B2C Digital Supplies (One-Stop Shop - OSS):** Taxed at the statutory destination rate of the consumer's EU Member State of residence.

---

### 3. Editorial Responsibility (Journalistic & Social Media Content)

Person responsible for editorial content pursuant to **§ 18 Abs. 2 MStV (Medienstaatsvertrag)** and the **EU Audiovisual Media Services Directive (AVMSD)**:

**Delia Lazarescu**  
Email: editorial@techunicorn.dev  

---

### 4. EU AI Act Transparency Declaration (Regulation (EU) 2024/1689)

Pursuant to **Article 50 of the EU Artificial Intelligence Act**:
1. This creator studio deploys general-purpose generative AI systems to assist with creative brainstorming, preliminary research, script drafting, and code compilation.
2. **Editorial Verification & Accountability:** All commercial content, published videos, software implementations, and client deliverables undergo human verification and editorial authorization by Delia Lazarescu.
3. Machine-readable detectability metadata (`X-AI-Generated: true`) is incorporated into digital exports.

---

### 5. Consumer Dispute Resolution & Online Dispute Resolution (ODR)

The European Commission provides an official platform for online dispute resolution (ODR):  
[https://ec.europa.eu/consumers/odr/](https://ec.europa.eu/consumers/odr/)  

We are neither obliged nor willing to participate in dispute resolution proceedings before a consumer arbitration board (Verbraucherschlichtungsstelle) pursuant to the Consumer Dispute Settlement Act (§ 36 VSBG). We resolve any commercial feedback directly and amicably with our clients.

---

### 6. Copyright & Intellectual Property

The contents, software tools, scripts, and video works published on this website and client portal are subject to international and European copyright law. Third-party contributions are identified as such. Reproduction, processing, distribution, or any form of commercialization beyond the scope of the copyright law requires the prior written consent of the creator.
MARKDOWN;

        return [
            'title' => 'Legal Notice (Impressum)',
            'effective_date' => $date,
            'contact_email' => 'legal@techunicorn.dev',
            'markdown' => $markdown,
        ];
    }
}
