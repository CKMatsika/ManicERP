import json
import re
import sys
from pathlib import Path

import pymupdf

sys.stdout.reconfigure(encoding="utf-8")

base = Path(r"C:\Users\User\Desktop\ManicERP\docs\sources")
for name in ["zfrm-soft-copy-1-1.pdf", "finance-act-2023-no-13.pdf"]:
    path = base / name
    doc = pymupdf.open(path)
    text = "\n\n".join(f"--- PAGE {i + 1} ---\n" + page.get_text() for i, page in enumerate(doc))
    (base / (path.stem + ".txt")).write_text(text, encoding="utf-8")
    print("=" * 80)
    print(name, "pages=", doc.page_count)
    print("metadata=", json.dumps(doc.metadata, indent=2)[:2000])
    print("--- first 1800 chars ---")
    print(text[:1800])
    for needle in ["1.2.2", "1.3.5", "Appendix 1", "Appendix 3", "Appendix 5", "urban", "rural district", "IPSAS 43", "Finance Act", "No. 13", "2023"]:
        hits = [m.start() for m in re.finditer(re.escape(needle), text, re.IGNORECASE)]
        print(f"MATCH {needle!r}: {len(hits)}")
