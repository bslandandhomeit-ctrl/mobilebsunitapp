"""Inventory model tables and explicit table references without exporting code/data."""
import collections
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parents[2]
PROJECTS = {
    'bsunits':pathlib.Path('/Users/ramonoem/Documents/project/webbsunitapp'),
    'beloan':pathlib.Path('/Users/ramonoem/Documents/project/beloan/html'),
}
audit = json.loads((ROOT/'outputs'/'unified-db'/'source-audit.json').read_text())
result = {}
for system,project in PROJECTS.items():
    tables = {row[0] for row in audit['sources'][system]['schema']}
    models = []
    explicit = collections.defaultdict(list)
    php_count = 0
    for path in (project/'app').rglob('*.php'):
        php_count += 1
        content = path.read_text(errors='replace')
        relative = str(path.relative_to(project))
        for match in re.finditer(r"protected\s+\$table\s*=\s*['\"]([^'\"]+)['\"]",content):
            table = match.group(1)
            physical = 'tb_'+table if system=='beloan' else table
            models.append({'file':relative,'declared_table':table,'physical_table':physical,
                           'table_present_in_source':physical in tables})
        for table in tables:
            # This measures lexical references, not the number of live SQL queries.
            count = len(re.findall(r'(?<![A-Za-z0-9_])'+re.escape(table)+r'(?![A-Za-z0-9_])',content))
            if count:
                explicit[table].append({'file':relative,'lexical_references':count})
    result[system] = {'app_php_files':php_count,'models':models,
        'explicit_table_references':dict(explicit),
        'limits':'Lexical inventory includes comments/legacy controllers; implicit Eloquent tables and dynamic queries need manual tracing.'}
output = ROOT/'outputs'/'unified-db'/'application-dependency-inventory.json'
output.write_text(json.dumps(result,indent=2))
print(json.dumps({s:{'app_php_files':d['app_php_files'],'declared_model_tables':len(d['models']),
    'tables_with_explicit_references':len(d['explicit_table_references'])} for s,d in result.items()},indent=2))
