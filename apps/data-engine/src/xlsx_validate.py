from __future__ import annotations

import zipfile
from pathlib import Path

REQUIRED_XLSX_PARTS = ("[Content_Types].xml", "xl/workbook.xml")
MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024
MAX_COMPRESSION_RATIO = 200


class InvalidXlsxError(ValueError):
    pass


def validate_xlsx_structure(path: str | Path) -> None:
    """Reject files that are merely ZIP-signed but not OOXML spreadsheets."""
    path = Path(path)
    try:
        with zipfile.ZipFile(path) as archive:
            names = set(archive.namelist())
            missing = [part for part in REQUIRED_XLSX_PARTS if part not in names]
            if missing:
                raise InvalidXlsxError(
                    f"Malformed XLSX: missing structural parts {missing}"
                )

            uncompressed = 0
            compressed = 0
            for info in archive.infolist():
                uncompressed += info.file_size
                compressed += info.compress_size or 1
                if uncompressed > MAX_UNCOMPRESSED_BYTES:
                    raise InvalidXlsxError("XLSX uncompressed size exceeds limit")

            if compressed > 0 and uncompressed / compressed > MAX_COMPRESSION_RATIO:
                raise InvalidXlsxError("XLSX compression ratio exceeds limit")
    except zipfile.BadZipFile as exc:
        raise InvalidXlsxError("Malformed ZIP/XLSX file") from exc
