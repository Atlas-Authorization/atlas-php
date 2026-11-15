#!/usr/bin/env python3
"""Generate the long-tail Backend API / Platform API surface of the PHP SDK from the
OpenAPI snapshot (modelled on sdks/rust/scripts/gen_catalog.py).

Usage (from sdks/php):

    python3 scripts/gen_catalog.py               # from scripts/openapi.snapshot.json (python3 scripts/refresh_snapshot.py updates it)
    python3 scripts/gen_catalog.py --spec FILE   # or a local copy
    python3 scripts/gen_catalog.py --freeze      # also pin every method name into catalog_pins.json

Reproducible: CI regenerates from the checked-in snapshot and fails on any diff.

Writes (never edit by hand):
  src/Generated/Catalog.php   `$atlas->catalog`: one property per resource group
  src/Generated/<Group>.php   one method per operation the hand-written resources do not cover
  tests/Generated/CatalogTest.php   every operation hits its method and path

Every operation the hand-written classes under src/ (everything but src/Generated) already
implement is skipped, except those listed in catalog_pins.json `generate_despite_handwritten`.

Method shape: path parameters first (`string`), then `array $body` (phpdoc array shape from
the schema), then `array $query` (shape), then `?string $idempotencyKey` when the operation
documents the header. Responses are decoded arrays with a phpdoc shape. Cursor lists also get
`<name>All(...)`, a Generator over every page.

Pins (catalog_pins.json): `names` METHOD+path -> published method name (kept even if the naming
rules change); `no_idempotency` ops that shipped without the key argument;
`generate_despite_handwritten` ops generated although a hand-written helper shares the route.
"""
import json, os, re, sys, glob

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
SNAP = os.path.join(HERE, 'openapi.snapshot.json')
if '--spec' in sys.argv:
    SNAP = sys.argv[sys.argv.index('--spec') + 1]
PINS_FILE = os.path.join(HERE, 'catalog_pins.json')
PINS = {'names': {}, 'no_idempotency': [], 'generate_despite_handwritten': []}
if os.path.exists(PINS_FILE):
    PINS.update(json.load(open(PINS_FILE)))

d = json.load(open(SNAP))
schemas = d['components']['schemas']
METHODS = ('get', 'post', 'put', 'patch', 'delete')


# ── hand-written coverage ────────────────────────────────────────────────────
def norm(p):
    return re.sub(r'/\*$', '/{*}', p)


def canon(p):
    return re.sub(r'\{[^}]*\}|\*', '{}', p).rstrip('/')


def handwritten():
    """(VERB, canonical path) pairs the hand-written code sends: a verb literal followed by its path expression."""
    out = set()
    for f in glob.glob(os.path.join(ROOT, 'src', '**', '*.php'), recursive=True):
        if os.sep + 'Generated' + os.sep in f:
            continue
        src = open(f).read()
        for m in re.finditer(r"""['"](GET|POST|PUT|PATCH|DELETE)['"]\s*,\s*((?:[^,()]|\([^()]*\))+)""", src):
            t = re.sub(r'(["\'])\s*\.\s*(?:[^"\'\n]|\([^()]*\))*?\s*\.\s*\1', '{}', m.group(2))
            t = re.sub(r'(/v1/[\w\-./]*/)(["\'])\s*\.\s*[\w$>()\[\]-]+', r'\1{}\2', t)
            t = re.sub(r'\{\$[^}"\']{0,80}\}', '{}', t)
            pm = re.search(r'/v1/[A-Za-z0-9_\-./{}:~]*', t)
            if pm:
                out.add((m.group(1), canon(pm.group(0).rstrip('.'))))
    return out


# ── schema -> phpdoc ─────────────────────────────────────────────────────────
def ref(s):
    return schemas.get(s['$ref'].split('/')[-1], {})


def merge_all(s, stack):
    props, req = {}, []
    for part in s['allOf']:
        part = deref(part, stack)
        props.update(part.get('properties', {}))
        req += part.get('required', [])
    return {'type': 'object', 'properties': props, 'required': req}


def deref(s, stack):
    while isinstance(s, dict) and '$ref' in s:
        s = ref(s)
    if isinstance(s, dict) and 'allOf' in s:
        s = merge_all(s, stack)
    return s if isinstance(s, dict) else {}


def merge_prop(a, b, stack):
    da, db = deref(a, stack), deref(b, stack)
    if 'properties' in da and 'properties' in db:
        props = dict(da['properties'])
        for pk, pv in db['properties'].items():
            props[pk] = merge_prop(props[pk], pv, stack) if pk in props else pv
        return {'type': 'object', 'properties': props}
    return a


def key(k):
    return k if re.fullmatch(r'[A-Za-z_]\w*', k) else "'" + k.replace("'", '') + "'"


def clean(t):
    return t.replace('*/', '* /')


def shape(s, depth=0, stack=()):
    if not isinstance(s, dict) or not s:
        return 'mixed'
    if '$ref' in s:
        name = s['$ref']
        if name in stack or depth > 11:
            return 'array<string,mixed>'
        stack = stack + (name,)
        s = ref(s)
        if not s:
            return 'mixed'
    if 'allOf' in s:
        s = merge_all(s, stack)
    for k in ('oneOf', 'anyOf'):
        if k in s:
            variants = [deref(q, stack) for q in s[k]]
            isnull = [v.get('type') == 'null' or v.get('const') is None and 'const' in v for v in variants]
            nul = any(isnull)
            keep = [q for q, n in zip(s[k], isnull) if not n]
            parts = []
            for q in keep:
                t = shape(q, depth + 1, stack)
                if t not in parts:
                    parts.append(t)
            objs = [deref(q, stack) for q in keep]
            if (len(parts) > 4 or 'mixed' in parts) and depth <= 11 and any('properties' in o for o in objs):
                # shortcut: object variants merge into one all-optional object (first type per key wins); other variants are dropped
                merged = {}
                for o in objs:
                    for pk, pv in o.get('properties', {}).items():
                        merged[pk] = merge_prop(merged[pk], pv, stack) if pk in merged else pv
                out = shape({'type': 'object', 'properties': merged}, depth + 1, stack)
            elif len(parts) > 4 or 'mixed' in parts or not parts:
                return 'mixed'
            else:
                out = '|'.join(parts)
            return out + '|null' if nul else out
    t = s.get('type')
    nullable = s.get('nullable') is True
    if isinstance(t, list):
        nullable = nullable or 'null' in t
        t = next((x for x in t if x != 'null'), None)
    if 'enum' in s and all(isinstance(e, str) and re.fullmatch(r'[\w.:\-]+', e) for e in s['enum']) and 0 < len(s['enum']) <= 12:
        out = "'" + "'|'".join(s['enum']) + "'"
    elif t == 'object' or 'properties' in s:
        props = s.get('properties')
        if props:
            req = set(s.get('required', []))
            if depth > 11:
                out = 'array<string,mixed>'
            else:
                out = 'array{' + ', '.join(f"{key(k)}{'' if k in req else '?'}:{shape(v, depth + 1, stack)}" for k, v in props.items()) + '}'
        elif isinstance(s.get('additionalProperties'), dict) and s['additionalProperties']:
            out = 'array<string,' + shape(s['additionalProperties'], depth + 1, stack) + '>'
        else:
            out = 'array<string,mixed>'
    elif t == 'array':
        out = 'list<' + shape(s.get('items', {}), depth + 1, stack) + '>'
    elif t == 'string':
        out = 'string'
    elif t == 'integer':
        out = 'int'
    elif t == 'number':
        out = 'int|float'
    elif t == 'boolean':
        out = 'bool'
    else:
        out = 'mixed'
    if nullable and out != 'mixed':
        out += '|null'
    return clean(out)


def json_schema(content):
    return (content or {}).get('application/json', {}).get('schema')


# ── naming ───────────────────────────────────────────────────────────────────
def words(s):
    return [w for w in re.split(r'[^A-Za-z0-9]+', s) if w]


def camel(s):
    w = words(s)
    return w[0].lower() + ''.join(x[:1].upper() + x[1:] for x in w[1:]) if w else ''


def pascal(s):
    return ''.join(x[:1].upper() + x[1:] for x in words(s))


def singular(w):
    if w.endswith('ies'):
        return w[:-3] + 'y'
    if w.endswith('sses') or w.endswith('uses'):
        return w[:-2]
    if w.endswith('s') and not w.endswith(('ss', 'us', 'is')):
        return w[:-1]
    return w


PHP_RESERVED = {'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class', 'clone', 'const', 'continue', 'declare', 'default', 'do', 'echo', 'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'interface', 'isset', 'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while', 'xor', 'yield'}


def group_of(segs):
    if segs[0] == 'platform' and len(segs) > 1:
        return 'platform_' + segs[1], segs[2:]
    return segs[0], segs[1:]


def base_name(method, tail, coll=False):
    lits = [t for t in tail if not t.startswith('{')]
    last_param = bool(tail) and tail[-1].startswith('{')
    p = ''.join(pascal(x) for x in lits[:-1]) + (pascal(singular(lits[-1])) if lits and last_param else (pascal(lits[-1]) if lits else ''))
    if method == 'get':
        return ('list' if coll and not last_param else 'get') + p
    if method == 'post':
        if not lits:
            return 'create' if not last_param else 'post'
        last = lits[-1]
        if last_param:
            return 'create' + p
        if last.endswith('s') and not last.endswith(('ss', 'status')) or len(lits) == 0:
            return 'create' + ''.join(pascal(x) for x in lits[:-1]) + pascal(singular(last))
        return camel(last) + ''.join(pascal(x) for x in lits[:-1])
    if method == 'put':
        return ('upsert' if last_param else 'set') + p
    if method == 'patch':
        return 'update' + p
    return 'delete' + p


# ── collect operations ───────────────────────────────────────────────────────
hw = handwritten()
ops = []
shaped = []
for path, item in sorted(d['paths'].items()):
    for m in METHODS:
        o = item.get(m)
        if not o or o.get('tags', [''])[0] not in ('BAPI', 'PLATFORM'):
            continue
        k = f'{m.upper()} {path}'
        if (m.upper(), canon(path)) in hw and k not in PINS['generate_despite_handwritten']:
            shaped.append((m, path, o))
            continue
        ops.append((m, path, o))

by_group = {}
for m, path, o in ops:
    segs = norm(path).strip('/').split('/')[1:]
    g, tail = group_of(segs)
    by_group.setdefault(g, []).append((m, path, o, tail))


def php_str(s):
    return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"


def path_expr(path):
    path = norm(path)
    parts = re.split(r'(\{[^}]+\})', path)
    out = []
    for p in parts:
        if not p:
            continue
        if p == '{*}':
            out.append('$wildcardPath')  # shortcut: the caller supplies the already-encoded sub-path
        elif p.startswith('{'):
            out.append('rawurlencode($' + pname(p[1:-1]) + ')')
        else:
            out.append(php_str(p))
    return ' . '.join(out)


def pname(n):
    if n == '*':
        return 'wildcardPath'
    c = camel(re.sub(r'[^A-Za-z0-9_\-]', '_', n)) or 'p'
    if c in ('body', 'query', 'idempotencyKey', 'this') or c in PHP_RESERVED:
        c += 'Id'
    return c


def doc_line(s):
    return clean(' '.join((s or '').split())).replace('\\', '\\\\') if s else ''


def build_method(m, path, o, tail, used):
    k = f'{m.upper()} {path}'
    pins = PINS['names']
    name = pins.get(k)
    if name is None:
        coll = any(isinstance(deref(r.get('content', {}).get('application/json', {}).get('schema', {}), ()).get('properties', {}).get('data'), dict) and deref(r['content']['application/json']['schema'], ())['properties']['data'].get('type') == 'array' or deref(r.get('content', {}).get('application/json', {}).get('schema', {}), ()).get('type') == 'array' for c, r in o.get('responses', {}).items() if c.startswith('2'))
        name = base_name(m, tail, coll)
        if name in used:
            ps = [t[1:-1] for t in tail if t.startswith('{')]
            alt = name + 'By' + ''.join(pascal(x) for x in ps[-1:]) if ps else name + m.capitalize()
            name = alt
        i = 2
        n0 = name
        while name in used or name in PHP_RESERVED - {'list', 'new'} and False:
            name = f'{n0}{i}'
            i += 1
    used.add(name)
    pnames = [pname(t[1:-1]) for t in tail if t.startswith('{')]
    # path may have params not in tail split (never: tail is all segments after group)
    all_params = re.findall(r'\{([^}]+)\}', norm(path))
    pnames = [pname(x) for x in all_params]
    for i, x in enumerate(pnames):  # duplicates
        if pnames.count(x) > 1:
            pnames[i] = x + str(i)
    rb = o.get('requestBody')
    args, docs = [], []
    body_arg = None
    raw_ct = None
    if rb:
        cts = list(rb.get('content', {}))
        sch = json_schema(rb.get('content'))
        if sch is not None or 'application/json' in cts:
            sd = deref(sch or {}, ())
            req = rb.get('required') is True and bool(sd.get('required'))
            isobj = sd.get('type') == 'object' or 'properties' in sd or not sd
            t = shape(sch or {}, 0, ())
            if isobj:
                body_arg = ('array $body' + ('' if req else ' = []'), t, 'body')
            else:
                body_arg = ('mixed $body = null', t, 'body')
        elif cts:
            raw_ct = cts[0]
    query = [p for p in o.get('parameters', []) if p.get('in') == 'query']
    idem = any(p.get('name') == 'Idempotency-Key' for p in o.get('parameters', [])) and k not in PINS['no_idempotency']
    # response
    resp = None
    raw_resp = False
    for code in sorted(o.get('responses', {})):
        if code.startswith('2'):
            r = o['responses'][code]
            c = r.get('content') or {}
            if 'application/json' in c:
                resp = shape(c['application/json'].get('schema'), 0, ())
            elif c:
                raw_resp = True
            break
    sig = ['string $' + x for x in pnames]
    if raw_ct:
        sig.append('string $bytes')
        sig.append("string $contentType = " + php_str(raw_ct))
    if body_arg:
        sig.append(body_arg[0])
    qshape = None
    if query:
        qshape = 'array{' + ', '.join(f"{key(p['name'])}?:{shape(p.get('schema', {}), 1, ())}" for p in query) + '}'
        sig.append('array $query = []')
    if idem:
        sig.append('?string $idempotencyKey = null')
    # ordering: optional-with-default after required (body required stays before defaults)
    lines = []
    summ = doc_line(o.get('summary')) or f'{m.upper()} {path}'
    desc = doc_line(o.get('description'))
    lines.append(f'    /**')
    lines.append(f'     * {summ}')
    lines.append(f'     *')
    lines.append(f'     * `{m.upper()} {path}`' + (f' (scope `{o["x-required-scope"]}`)' if o.get('x-required-scope') else ''))
    if desc and desc != summ:
        lines.append(f'     *')
        lines.append(f'     * {desc}')
    lines.append('     *')
    for x, n in zip(all_params, pnames):
        lines.append(f'     * @param string ${n} the `{x}` path segment')
    if body_arg:
        lines.append(f'     * @param {body_arg[1]} $body')
    if qshape:
        lines.append(f'     * @param {qshape} $query')
    if idem:
        lines.append('     * @param string|null $idempotencyKey sent as `Idempotency-Key`; a retry with the same key replays the first response')
    ret = 'string' if raw_resp else ('void' if resp is None else 'array')
    if raw_resp:
        lines.append('     * @return string the raw response body')
    elif resp is not None:
        lines.append(f'     * @return {resp if resp.startswith(("array", "list")) else "array<string,mixed>"}')
    lines.append('     */')
    call = [php_str(m.upper()), path_expr(path)]
    if qshape:
        call.append('query: $query')
    if raw_ct:
        call.append('rawBody: $bytes')
        call.append('contentType: $contentType')
    elif body_arg:
        call.append('body: $body')
    if idem:
        call.append('idempotencyKey: $idempotencyKey')
    if raw_resp:
        call.append('raw: true')
    expr = '$this->http->request(' + ', '.join(call) + ')'
    # reorder signature so required params precede defaulted ones
    req_sig = [s for s in sig if '=' not in s]
    opt_sig = [s for s in sig if '=' in s]
    lines.append(f"    public function {name}({', '.join(req_sig + opt_sig)}): {ret}")
    lines.append('    {')
    if ret == 'void':
        lines.append(f'        {expr};')
    elif ret == 'array':
        lines.append(f'        $r = {expr};')
        lines.append('')
        lines.append('        return is_array($r) ? $r : [];')
    else:
        lines.append(f'        return (string) {expr};')
    lines.append('    }')
    out = '\n'.join(lines)
    # cursor pagination
    qn = {p['name'] for p in query}
    is_list = False
    if m == 'get' and 'starting_after' in qn and resp and re.search(r'\bdata\??:list<', resp) and re.search(r'\bnext_cursor\??:', resp):
        is_list = True
    extra = None
    if is_list:
        aname = name + 'All'
        sigs = ['string $' + x for x in pnames] + ['array $query = []']
        lines2 = ['    /**', f'     * Every item of `{name}` across all pages (cursor pagination), one at a time.', '     *']
        for x, n in zip(all_params, pnames):
            lines2.append(f'     * @param string ${n} the `{x}` path segment')
        lines2.append(f'     * @param {qshape} $query')
        lines2.append('     * @return \\Generator<int,mixed>')
        lines2.append('     */')
        lines2.append(f"    public function {aname}({', '.join(sigs)}): \\Generator")
        lines2.append('    {')
        args2 = ', '.join(['$' + x for x in pnames] + ['$q'])
        lines2.append(f'        return \\Atlas\\Pagination::iterate(fn (array $q): array => $this->{name}({args2}), $query);')
        lines2.append('    }')
        extra = '\n'.join(lines2)
        used.add(aname)
    return name, out, extra, (m.upper(), path, pnames, bool(body_arg and body_arg[0].startswith('array $body') and ' = ' not in body_arg[0]), bool(raw_ct), idem, ret, name)


HEADER = '<?php\n\ndeclare(strict_types=1);\n\n// GENERATED by scripts/gen_catalog.py from the OpenAPI snapshot: do not edit by hand.\n\n'
GEN = os.path.join(ROOT, 'src', 'Generated')
os.makedirs(GEN, exist_ok=True)
for f in glob.glob(os.path.join(GEN, '*.php')):
    os.remove(f)

classes = {}
meta = []
frozen = {}
for g in sorted(by_group):
    cls = pascal(g)
    used = set()
    body = []
    for m, path, o, tail in by_group[g]:
        name, text, extra, mt = build_method(m, path, o, tail, used)
        frozen[f'{m.upper()} {path}'] = name
        body.append(text)
        if extra:
            body.append(extra)
        meta.append((cls, mt))
    classes[g] = cls
    code = HEADER + 'namespace Atlas\\Generated;\n\nuse Atlas\\Http;\n\n/** `/v1/' + g.replace('platform_', 'platform/') + '` operations (generated). */\nfinal class ' + cls + '\n{\n    public function __construct(private readonly Http $http)\n    {\n    }\n\n' + '\n\n'.join(body) + '\n}\n'
    open(os.path.join(GEN, cls + '.php'), 'w').write(code)

props = '\n'.join(f'    public readonly {c} ${camel(g)};' for g, c in sorted(classes.items()))
inits = '\n'.join(f'        $this->{camel(g)} = new {c}($http);' for g, c in sorted(classes.items()))
cat = HEADER + 'namespace Atlas\\Generated;\n\nuse Atlas\\Http;\n\n/**\n * The long tail of the Backend API: every operation the hand-written resources on `Atlas\\Client` do not cover,\n * one property per resource group: `$atlas->catalog->waitlist...`.\n */\nfinal class Catalog\n{\n' + props + '\n\n    public function __construct(Http $http)\n    {\n' + inits + '\n    }\n}\n'
open(os.path.join(GEN, 'Catalog.php'), 'w').write(cat)

# test: every operation hits its method and path
# Shapes for operations the hand-written resources implement: importable phpdoc aliases.
sh = []
for m, path, o in shaped:
    base = 'Op' + m.capitalize() + ''.join(pascal(x.strip('{}*') or 'wildcard') + ('Param' if x.startswith('{') else '') for x in path.strip('/').split('/')[1:])
    rb = json_schema((o.get('requestBody') or {}).get('content'))
    if rb is not None:
        sh.append(f' * @phpstan-type {base}Body {shape(rb)}')
    q = [x for x in o.get('parameters', []) if x.get('in') == 'query']
    if q:
        sh.append(f' * @phpstan-type {base}Query array{{' + ', '.join(f"{key(x['name'])}?:{shape(x.get('schema', {}), 1)}" for x in q) + '}')
    for code in sorted(o.get('responses', {})):
        if code.startswith('2'):
            sc = json_schema(o['responses'][code].get('content'))
            if sc is not None:
                sh.append(f' * @phpstan-type {base}Response {shape(sc)}')
            break
open(os.path.join(GEN, 'Shapes.php'), 'w').write(HEADER + 'namespace Atlas\\Generated;\n\n/**\n * Request, query and response shapes of the operations the hand-written resources implement, as importable\n * phpdoc aliases (`@phpstan-import-type OpGetV1InstanceResponse from \\Atlas\\Generated\\Shapes`).\n *\n' + '\n'.join(sh) + '\n */\nfinal class Shapes\n{\n}\n')

rows = []
for cls, (m, path, pn, body_req, raw, idem, ret, name) in meta:
    rows.append(f"        [{php_str(camel(next(g for g, c in classes.items() if c == cls)))}, {php_str(name)}, {php_str(m)}, {php_str(norm(path))}, {len(pn)}, {'true' if body_req else 'false'}, {'true' if raw else 'false'}],")
test = HEADER + '''namespace Atlas\\Tests\\Generated;

use Atlas\\Generated\\Catalog;
use Atlas\\Http;
use Atlas\\Tests\\Support\\MockClient;
use GuzzleHttp\\Psr7\\HttpFactory;
use GuzzleHttp\\Psr7\\Response;
use PHPUnit\\Framework\\TestCase;

final class CatalogTest extends TestCase
{
    /** @return list<array{string,string,string,string,int,bool,bool}> group, method, HTTP method, path template, path params, body required, raw body */
    public static function operations(): array
    {
        return [
''' + '\n'.join(rows) + '''
        ];
    }

    /** @dataProvider operations */
    public function testEveryOperationHitsItsMethodAndPath(string $group, string $method, string $verb, string $path, int $params, bool $bodyRequired, bool $raw): void
    {
        $mock = new MockClient();
        $mock->push(200, []);
        $f = new HttpFactory();
        $catalog = new Catalog(new Http('sk_test_x', 'https://api.example.test', $mock, $f, $f));
        $args = [];
        for ($i = 0; $i < $params; $i++) {
            $args[] = 'p' . $i;
        }
        if ($raw) {
            $args[] = 'bytes';
        }
        if ($bodyRequired) {
            $args[] = [];
        }
        $catalog->$group->$method(...$args);
        $seen = $mock->requests[0];
        self::assertSame($verb, $seen->getMethod());
        $expected = preg_replace_callback('/\\{[^}]+\\}/', static function () { static $n = 0; return 'p' . $n++; }, $path);
        self::assertSame($expected, $seen->getUri()->getPath());
    }
}
'''
os.makedirs(os.path.join(ROOT, 'tests', 'Generated'), exist_ok=True)
open(os.path.join(ROOT, 'tests', 'Generated', 'CatalogTest.php'), 'w').write(test)

if '--freeze' in sys.argv or not os.path.exists(PINS_FILE):
    PINS['names'] = {**frozen, **PINS['names']}
    json.dump(PINS, open(PINS_FILE, 'w'), indent=1, sort_keys=True)
    open(PINS_FILE, 'a').write('\n')
# a pin the spec no longer reaches fails the run
stale = [k for k in PINS['names'] if k not in frozen and (k.split(' ', 1)[0].lower(), k.split(' ', 1)[1]) not in {(m, p) for m, p, _ in [(a, b, c) for a, b, c in ops]}]
if stale:
    sys.exit('stale pins: ' + ', '.join(stale[:5]))
print(f'{len(ops)} operations in {len(classes)} groups')
