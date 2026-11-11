import re
import sys
from pathlib import Path

import pymupdf

sys.stdout.reconfigure(encoding="utf-8")

base = Path(r"C:\Users\User\Desktop\ManicERP\docs\sources")

def contexts(pdf_name, needles, window=700):
    doc = pymupdf.open(base / pdf_name)
    print("#" * 100)
    print(pdf_name)
    for needle in needles:
        print("\n===== NEEDLE:", needle, "=====")
        found = 0
        for i, page in enumerate(doc):
            text = page.get_text()
            for m in re.finditer(re.escape(needle), text, re.IGNORECASE):
                start = max(0, m.start() - window // 2)
                end = min(len(text), m.end() + window)
                snippet = re.sub(r"\s+", " ", text[start:end])
                print(f"[page {i + 1}] ...{snippet}...")
                found += 1
                if found >= 4:
                    break
            if found >= 4:
                break

contexts("zfrm-soft-copy-1-1.pdf", [
    "1.2.2", "1.3.5", "first edition", "Accountant-General", "Issued by",
    "Appendix 1", "Appendix 3", "Appendix 5", "expenses by nature",
    "functional", "changes in net assets", "budget", "reporting currency",
])
contexts("finance-act-2023-no-13.pdf", [
    "FINANCE ACT, 2023", "Short title", "assented", "commencement",
    "rate of tax", "2024", "No. 13",
])
