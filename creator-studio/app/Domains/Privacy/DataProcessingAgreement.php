<?php

namespace App\Domains\Privacy;

use App\Domains\Agents\Models\AgentEvent;
use App\Domains\Security\Audit;
use App\Models\Deal;
use App\Models\User;
use App\Services\Prompts;

/**
 * Enterprise Self-Serve Data Processing Agreement (DPA) Engine.
 * Fulfills Regulation (EU) 2016/679 (GDPR) Article 28 and incorporates
 * European Commission Standard Contractual Clauses (SCCs - Decision 2021/914/EU Module 2).
 */
class DataProcessingAgreement
{
    public const VERSION = '2026.11';

    /**
     * Generate complete DPA Markdown populated with client corporate data.
     */
    public static function dpaMarkdown(Deal $deal, ?User $client = null): string
    {
        $creator = Prompts::name();
        $brand = $deal->brand_name;
        $date = $deal->dpa_signed_at?->format('F j, Y') ?: now()->format('F j, Y');
        $version = self::VERSION;

        $clientUser = $client ?: $deal->client;
        $vault = $clientUser?->brandVault;

        $clientCompany = $vault?->brand_name ?: ($clientUser?->company ?: $brand);
        $clientAddress = $vault?->billing_address ? str_replace("\n", ', ', trim($vault->billing_address)) : 'Address on corporate file';
        $clientVat = $vault?->tax_id ?: 'Tax ID on corporate file';
        $clientContact = $clientUser ? "{$clientUser->name} ({$clientUser->email})" : "{$deal->contact_name} ({$deal->contact_email})";

        $hashNote = $deal->dpa_signature_hash
            ? "**Cryptographic Signature Hash (SHA-256):** `{$deal->dpa_signature_hash}`  \n**Signed by:** {$deal->dpa_signer_name} ({$deal->dpa_signer_email}) on {$deal->dpa_signed_at?->toIso8601String()}\n"
            : "*Pending electronic execution via Creator Studio Deal Room.*\n";

        return <<<MARKDOWN
# Data Processing Agreement (GDPR Article 28 & EU SCCs)

**Agreement Version:** {$deal->id}-DPA-{$date} (Version {$version})  
**Effective Date:** {$date}  

---

### PARTIES TO THIS AGREEMENT:

1. **The Data Controller ("Controller", "Client"):**  
   **Legal Entity:** {$clientCompany}  
   **Registered Address:** {$clientAddress}  
   **Tax / VAT ID:** {$clientVat}  
   **Authorized Contact:** {$clientContact}  

2. **The Data Processor ("Processor", "Creator"):**  
   **Legal Entity:** {$creator} Creator Studio  
   **Authorized Representative:** Delia Lazarescu  
   **Contact Email:** privacy@techunicorn.dev / legal@techunicorn.dev  
   **EU VAT ID:** Registered Intra-Community VAT on file (Article 214 Directive 2006/112/EC)  

{$hashNote}

---

### 1. Purpose & Scope (Article 28 GDPR)

1.1 This Data Processing Agreement ("DPA") governs all processing of personal data by Processor on behalf of Controller in connection with the sponsorship, media placement, creative software development, and video production services agreed in Deal #{$deal->id}.  
1.2 Both parties agree to abide by the **General Data Protection Regulation (Regulation (EU) 2016/679 - GDPR)**, the **ePrivacy Directive 2002/58/EC**, and applicable European data protection legislation.

---

### 2. Obligations of the Data Processor

Pursuant to Article 28(3) of the GDPR, Processor covenants and undertakes to:

1. **Documented Instructions (Art. 28(3)(a)):** Process personal data solely on documented instructions from Controller, including with regard to transfers of personal data to third countries or international organizations.
2. **Confidentiality (Art. 28(3)(b)):** Ensure that persons authorized to process the personal data have committed themselves to confidentiality or are under an appropriate statutory obligation of confidentiality.
3. **Security Measures (Art. 28(3)(c) & Art. 32):** Implement technical and organizational measures to ensure a level of security appropriate to the risk (AES-256 encryption at rest, TLS 1.3 in transit, binary magic-byte inspection, multi-factor authentication).
4. **Sub-processors (Art. 28(3)(d)):** Adhere to conditions for engaging other processors. An up-to-date directory of authorized sub-processors is maintained in the Creator Studio Trust Center. Controller is notified of changes with a statutory right to object.
5. **Assisting the Controller (Art. 28(3)(e)):** Assist Controller by appropriate technical and organizational measures in fulfilling Controller’s obligation to respond to requests exercising data subject rights under Chapter III GDPR (Articles 15–22).
6. **Deletion or Return of Data (Art. 28(3)(g)):** At the choice of Controller, delete or return all personal data to Controller after the end of the provision of services, and delete existing copies unless statutory commercial tax retention laws (Directive 2006/112/EC) require storage.
7. **Audit & Compliance (Art. 28(3)(h)):** Make available to Controller all information necessary to demonstrate compliance and allow for and contribute to audits.

---

### 3. International Data Transfers (Standard Contractual Clauses - SCCs)

Where data is processed outside the European Economic Area (EEA), the parties hereby incorporate by reference the **European Commission Standard Contractual Clauses (Decision 2021/914/EU - Module 2: Controller-to-Processor)**:
- **Competent Supervisory Authority:** The Data Protection Commission (DPC) or the supervisory authority of the Member State in which Controller is established.
- **Docking Clause (Clause 7):** Permitted.
- **Governing Law:** The law of the EU Member State in which Controller is established.

---

### 4. Technical and Organizational Security Measures (TOMs)

1. **Access Control:** Role-Based Access Control (RBAC) enforced with least-privilege principles.
2. **Cryptographic Protection:** SHA-256 non-repudiation contract hashing; AES-256 encrypted token vaults.
3. **Transmission Security:** Enforced HTTPS with HTTP Strict Transport Security (HSTS) and dynamic Content Security Policy (CSP).
4. **Data Redaction:** In-flight automated PII and credentials sanitizer masking API keys and private tokens.
5. **Zero Data Retention:** Transient in-memory LLM processing without model training.

---

### APPENDIX 1: DETAILS OF DATA PROCESSING

- **Categories of Data Subjects:** Client employees, marketing managers, procurement representatives, software developers.
- **Categories of Personal Data:** Corporate names, business emails, billing addresses, tax IDs, project feedback annotations, timecode review pins.
- **Special Categories of Data (Sensitive Data):** None.
- **Nature and Purpose of Processing:** Preparing sponsorship integrations, coordinating video production review, invoice settlement, and audience attribution tracking.
- **Retention Period:** Active throughout the duration of Deal #{$deal->id}, with ephemeral creative files auto-purged within 60 days of settlement, and tax transaction records retained for statutory commercial audit periods.

---

### EXECUTION & TAMPER-EVIDENT ELECTRONIC SIGNATURE

Both parties acknowledge that cryptographic execution via the Creator Studio platform constitutes a valid, legally enforceable electronic signature under **Regulation (EU) No 910/2014 (eIDAS)**.
MARKDOWN;
    }

    /**
     * Cryptographically execute the DPA for a deal.
     */
    public static function sign(Deal $deal, string $signerName, string $signerEmail, string $ipAddress): array
    {
        $timestamp = now();
        $rawDoc = self::dpaMarkdown($deal);
        $signaturePayload = "{$deal->id}|{$deal->brand_name}|{$signerName}|{$signerEmail}|{$ipAddress}|{$timestamp->toIso8601String()}|".hash('sha256', $rawDoc);
        $signatureHash = hash('sha256', $signaturePayload);

        $deal->update([
            'dpa_signed_at' => $timestamp,
            'dpa_signer_name' => $signerName,
            'dpa_signer_email' => strtolower(trim($signerEmail)),
            'dpa_signature_hash' => $signatureHash,
        ]);

        Audit::log('deal_linked', $deal->client, $deal, [
            'dpa_signed' => true,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'signature_hash' => $signatureHash,
            'ip' => $ipAddress,
        ]);

        AgentEvent::log('lead', "GDPR Article 28 DPA cryptographically signed for {$deal->brand_name} by {$signerName} ({$signatureHash}).");

        return [
            'ok' => true,
            'signed' => true,
            'deal_id' => $deal->id,
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'signed_at' => $timestamp->toIso8601String(),
            'signature_hash' => $signatureHash,
            'message' => 'Data Processing Agreement (DPA) successfully executed under GDPR Article 28.',
        ];
    }
}
