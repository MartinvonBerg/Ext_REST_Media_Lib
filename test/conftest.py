from datetime import datetime
from pathlib import Path
import pytest


@pytest.hookimpl(tryfirst=True)
def pytest_configure(config):
    # set custom options only if none are provided from command line
    if not config.option.htmlpath:
        now = datetime.now()
        # create report target dir
        reports_dir = Path('reports', now.strftime('%Y%m%d'))
        reports_dir = Path('testreports')
        reports_dir.mkdir(parents=True, exist_ok=True)
        # custom report file
        report = reports_dir / f"report-{now.strftime('%Y-%m-%d-%H%M%S')}.html"
        # adjust plugin options
        config.option.htmlpath = report
        config.option.self_contained_html = True