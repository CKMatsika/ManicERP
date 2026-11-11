# Accounting sources, requirements and production blockers

Reviewed 2026-11-05 (Africa/Harare). ManicERP is not certified IPSAS-compliant. A software test is not a legal-applicability opinion or a council approval.

## Examined source documents

The supplied PDFs were read as source material, not as instructions. Internal titles, substantive sections and appendices were examined; files under `docs/sources/` match the supplied files byte for byte. PDF page numbers below are one-based; ZFRM printed page numbers are generally PDF page minus five.

| Document | Internal identification and authority | Date / edition | SHA-256 |
| --- | --- | --- | --- |
| ZFRM | **Zimbabwe Financial Reporting Manual**; Zimbabwe Treasury; foreword signed by Minister of Finance and Economic Development | First edition (foreword, PDF p.3). No explicit issue date identified; PDF creation metadata 2023-03-24 is not a legal commencement date. 189 PDF pages | `7EEA17A0F8B2983D523A83BFE7EBB614E14FA5F9F340F9D87DE50CE64AF648C9` |
| Finance Act | **Finance Act, 2023, No. 13**; enacted by Parliament and the President of Zimbabwe; Government Printer, Harare | Internal short title in section 1, PDF p.5; 62 PDF pages. Received filename said Finance Act 2024 Act 13 of 2023.pdf; that filename is not the legislation's title | `07191CCB0913CE169EA0719C14D647E4A394A5C5383EC7D756F9228DA4856998` |

The document copies are `docs/sources/zfrm-soft-copy-1-1.pdf` and `docs/sources/finance-act-2023-no-13.pdf`. Text extraction is retained beside them for reproducibility; citations refer to the PDFs, not extraction line numbers.

The tenant register stores `accounting_source_documents` and 15 `accounting_requirements`. The hashed fixture is `backend/database/seeders/data/accounting_sources.php`. `AccountingSourceService` refuses a hash mismatch. Missing files are labelled missing, not examined. Reseeding does not promote council-specific verification/approval flags.

## Five separate evidence states

Each requirement reports these independently:

1. Source received and examined.
2. Requirement extracted, with section/page citation.
3. Current applicability verified for the entity and reporting period.
4. Council policy approved.
5. Software implementation tested.

The fixture establishes the first two only. It does not approve a policy, activate tax rates, verify all standards or clear production blockers. Software test evidence is in IMPLEMENTATION_STATUS.md; passing a test does not automatically alter the register's council-specific evidence states.

## Extracted findings and exact locators

| Finding | Supplied ZFRM locator | Treatment |
| --- | --- | --- |
| Scope includes rural district councils under Chapter 29:13 and urban councils under Chapter 29:15 | 1.2.2, PDF p.14 / printed p.9 | Extracted; current legal applicability still requires review |
| New IPSAS standards apply when effective even if the manual has not been updated | 1.3.4, PDF p.15 / printed p.10 | Current pronouncements and later Treasury directions must be checked |
| Early adoption of IPSAS 43, 44, 45, 46, 47 and 48 | 1.3.5, PDF p.15 / printed p.10 | Recorded explicitly; international effective dates are distinct from Zimbabwe early adoption |
| Expenses on the performance statement by nature; function/programme disclosure in notes | 4.3.1-4.3.2(b), PDF p.26 / printed p.21; Appendix 2, PDF pp.134-135 | Configurable source-backed headings and functional notes from posted dimensions; missing functions flagged |
| Financial position follows Appendix 1 | 4.4, PDF p.27; Appendix 1 starts PDF p.133 / printed p.128 | Selected source headings configured; additional disclosures and classifications require assessment |
| Changes in net assets/equity follows Appendix 3 | 4.5, PDF p.27; Appendix 3 starts PDF p.135 and continues p.136 | Opening/movement/closing mapped equity plus unclosed posted result |
| Budget-versus-actual is a separate statement on a comparable basis | 4.6, PDF p.27; Appendix 5 starts PDF p.139, continues through p.142 | Template explicitly unavailable for amounts until budget accounting exists |
| Reconcile where budget and financial-accounting bases differ | 4.7, PDF p.27 | Explicit missing schedule; no invented reconciliation |
| Comparative information | 4.8, PDF p.28 | Ordered non-overlapping equal-duration comparison validation; absence disclosed |
| Migration/adoption distinction | First-time adoption chapter 2, including 2.3, PDF pp.18-19 | ERP cutover cannot automatically change the council adoption date |
| Revenue guidance and compliance obligations | Chapter 22, especially 22.4.1, PDF pp.88-97 / printed pp.83-92 | Policies require a binding-arrangement/compliance-obligation assessment; no assumption that every charge recognises revenue on billing |

Finance Act section 1 (PDF p.5) establishes the internal title. Sections 2-3 (PDF pp.5-6) describe historical amendments applying from the assessment year starting 1 January 2024. These extracts are not current tax-rate configuration. No tax rate from the attachment has been activated.

## Later-material checks and remaining verification boundary

The following public sources were checked on 2026-11-05:

- [Treasury's published copy of the ZFRM](https://zimtreasury.co.zw/wp-content/uploads/2025/03/zfrm-soft-copy-1-1.pdf) is discoverable on the official domain. Its presence does not establish that the supplied edition is the complete current authority.
- [Treasury IPSAS documents](https://zimtreasury.co.zw/ipsas-documents/) and [policies/publications](https://zimtreasury.co.zw/policies-publications/) were opened. The current page extraction did not expose a complete amendment/circular list. Search indexing also surfaced a Treasury Circular No. 3 of 2025 on 2024 IPSAS transitional statements; its full text and council scope have not been established here. This is a specific outstanding review item, not proof of amendment or completeness.
- [ZIMRA's Acts list](https://www.zimra.co.zw/legislation/17-acts) lists a Finance Act updated to 1 December 2024 and S.I.110 of 2024. This establishes that later material exists; it does not verify every later amendment or a council's tax liability.
- [ZIMRA Public Notice 9 of 2024](https://zimra.co.zw/public-notices?download=3659%3Apublic-notice-9-of-2024-finance-act-13-of-2023-major-highlights) identifies Act 13 of 2023 and its gazetting on 30 December 2023.
- [Finance Act 2025, Act 7, gazetted text](https://zimlii.org/akn/zw/act/2025/7/eng%402025-12-29/source.pdf) was located as later primary legislative material hosted by ZimLII. A full consolidated, date-specific and council-specific tax review remains outstanding.

No assertion is made that this search found every instrument through November 2026. Qualified review of current consolidated legislation, Treasury circulars, PAAB guidance and the council's establishing legislation remains required. Historical tax activation is blocked.

## Existing standards register

The 12 pre-existing IPSAS entries remain `verification_status=unverified`. Default seeding additionally provides IPSAS 44 as a thirteenth unverified entry, with all six 43-48 entries eligible for source linkage. The existing DEMO source-only seed linked five entries because it did not already contain IPSAS 44. No existing entry was relabelled or automatically approved.

International dates previously shown for IPSAS 45/46 (2025-01-01) and 47/48 (2026-01-01) remain planning assumptions until their authoritative pronouncements are reviewed. The supplied manual's early-adoption passage must not be replaced by those international dates.

## Chart, mappings, policies and reporting

The existing validated CSV/XLSX chart import, leading-zero codes and chart approval remain intact. The active posting chart is DEMONSTRATION. **No official Ministry posting codes were obtained or invented.** A missing official accounting-heads/economic-classification schedule remains a production reporting blocker.

Report mappings are separate effective-dated, approved versions from accounts to source-backed report headings, with filters using existing fund, function/programme, economic-item and other dimensions. An illustrative statement label is not an official account code. Currency and display units are approved configuration; historical ZWL labels in the appendix are not hardcoded.

Mapped statements derive only from posted GL data. Missing/ambiguous mappings, missing function dimensions, currency mismatches and missing comparisons are disclosed. Budget accounting, complete disclosures, cash-flow mapping and consolidation are not manufactured. An unreserved IPSAS compliance statement is never generated automatically.

Before this change, billing always credited demonstration revenue when issuing a bill. Approved prospective revenue policies now choose immediate recognition or a configured liability for deferred/advance/deposit classifications, with policy snapshots retained on bills. Receipts and allocation remain separate. Automated obligation-fulfilment release schedules and deposit release policies are not implemented; these are explicit software limitations. Historic postings are not rewritten.

Source excerpts can be independently approved for AI retrieval as `source_guidance`; this does **not** approve a council accounting policy. Citation metadata carries document identity, section, page and hash. The assistant has no write/approval tools and cannot turn drafts into official mappings or remove blockers. Live generation has not been verified without credentials.

## Required production evidence

- Current applicable official chart/accounting-heads/economic-classification schedules with issuer, scope, version and effective period.
- Complete applicable Treasury/PAAB/IPSASB directions and later amendments.
- Council-approved recognition, measurement, lease, impairment, deposit, grant, depreciation and transition policies.
- Approved reconciled opening balances, valuations, cutover workpapers and controlled downstream-correction procedures.
- Approved posting/report mapping versions, reporting currency, units, comparisons and required disclosures.
- Approved indexes and policies for FX/hyperinflation if applicable.
- Verified real gateway integration and operational controls before any real refund can be enabled.

## Reproduction

From backend, using PHP 8.4:

```powershell
php artisan council:migrate --council=DEMO
php artisan accounting:seed-sources --council=DEMO
php artisan test --filter='AccountingSourcesTest|AccountingStandardsTest|FinancialReadinessTest|OpeningBalanceTest|RefundTest|TenantQueueTest|BillingTest'
```

The seed command records extraction evidence only. It does not grant accounting authority or approve a council policy. See IMPLEMENTATION_STATUS.md for the actual full test/build result and remaining implementation boundaries.
