#!/usr/bin/env python3
"""HTTP route assertions against the disposable site; no additional packages."""
import json
import re
import sys
import urllib.error
import urllib.request
from pathlib import Path

manifest = json.loads(Path(sys.argv[1]).read_text())
phase = sys.argv[2] if len(sys.argv) > 2 else 'default'
community = json.loads(Path(sys.argv[3]).read_text()) if len(sys.argv) > 3 else None
base = manifest['base'].rstrip('/')
assert base == 'http://127.0.0.1:8091', 'Refuse the main site'

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None

opener = urllib.request.build_opener(NoRedirect())
report = []
errors = []

def _request(path, status, post_id=None, post_type=None, location=None, canonical=None):
    url = base + path
    try:
        result = opener.open(url, timeout=20)
    except urllib.error.HTTPError as error:
        result = error
    body = result.read().decode('utf-8')
    assert result.code in (status if isinstance(status, tuple) else (status,)), (path, result.code, status)
    if post_id is not None:
        assert result.headers['X-Aloha-Test-Object-ID'] == str(post_id), (path, dict(result.headers))
    if post_type == 'not-area':
        assert result.headers.get('X-Aloha-Test-Post-Type') != 'area', path
    elif post_type:
        assert result.headers['X-Aloha-Test-Post-Type'] == post_type, path
    if result.code == 404:
        assert result.headers['X-Aloha-Test-Is-404'] == '1', path
    if location:
        assert result.headers['Location'] == base + location, (path, result.headers.get('Location'))
    if canonical:
        assert re.search(r'<link\b(?=[^>]*rel=[\"\']canonical[\"\'])(?=[^>]*href=[\"\']' + re.escape(base + canonical) + r'[\"\'])[^>]*>', body), path
    report.append({'path': path, 'status': result.code, 'id': result.headers.get('X-Aloha-Test-Object-ID'), 'type': result.headers.get('X-Aloha-Test-Post-Type'), 'location': result.headers.get('Location')})

def request(*args, **kwargs):
    try:
        _request(*args, **kwargs)
    except AssertionError as error:
        errors.append(str(error))

if phase == 'default':
    for slug, post_id in manifest['areas'].items():
        path = '/big-island/' + slug + '/'
        request(path, 200, post_id, 'area', canonical=path)
        request(path[:-1], 301, location=path)
    for path, post_id in manifest['pages'].items():
        request('/' + path + '/', 200, post_id, 'page')
    for suffix in ['does-not-exist', 'mauna', 'fixture-draft-area', 'fixture-private-area', 'page', 'attachment', 'mauna-lani/123', 'mauna-lani/embed', 'mauna-lani/attachment/photo']:
        request('/big-island/' + suffix + '/', 404)
    # Existing parent Pages retain their own Core feed/embed/trackback endpoints.
    for suffix in ['feed', 'rss2', 'embed', 'trackback', '123']:
        request('/big-island/' + suffix + '/', (200, 301, 302, 404), post_type='not-area')
    request('/?post_type=area&p=' + str(manifest['draft']), 404)
    request('/?post_type=area&p=' + str(manifest['private']), 404)
    request('/big-island/mauna-lani/fixture-community/', 200, manifest['community'], 'community')
    request('/?post_type=community&p=' + str(manifest['community']), 301, location='/big-island/mauna-lani/fixture-community/')
    request('/area/', 404)
    request('/?area=mauna-lani', 200, manifest['areas']['mauna-lani'], 'area', canonical='/big-island/mauna-lani/')
elif phase == 'configured':
    request('/island-test/mauna-lani/', 200, manifest['areas']['mauna-lani'], 'area', canonical='/island-test/mauna-lani/')
    request('/big-island/mauna-lani/', 404)
    request('/big-island/guide/', 200, manifest['pages']['big-island/guide'], 'page')
elif phase == 'restored':
    request('/big-island/mauna-lani/', 200, manifest['areas']['mauna-lani'], 'area')
    request('/island-test/mauna-lani/', 404)
elif phase == 'moved' and community:
    pass
else:
    raise AssertionError('Unknown phase')
if community:
    assert community['base'].rstrip('/') == base
    if phase == 'default':
        path = '/big-island/mauna-lani/ka-milo/'
        request(path, 200, community['published'], 'community', canonical=path)
        request(path[:-1], 301, location=path)
        request('/big-island/mauna-lani/fixture-old-community/', 404)
        for suffix in ['waikoloa-beach-resort/ka-milo/', 'waikoloa-beach-resort/ka-milo', 'no-such-area/ka-milo/', 'mauna-lani/no-such-community/', 'mauna-lani/ka/', 'fixture-draft-area/ka-milo/', 'fixture-private-area/ka-milo/', 'mauna-lani/fixture-draft-community/', 'mauna-lani/fixture-private-community/', 'mauna-lani/ka-milo/embed/', 'guide/no-such-rubric/', 'guide/no-such-rubric/article/', 'communities/ka-milo/']:
            request('/big-island/' + suffix, 404)
        for kind in community['invalid']:
            request('/big-island/mauna-lani/fixture-invalid-' + kind + '/', 404)
        for kind in ['draft', 'private']:
            request('/big-island/fixture-' + kind + '-parent/fixture-invalid-' + kind + '/', 404)
        for key in ['draft', 'private', 'unassigned']:
            request('/?post_type=community&p=' + str(community[key]), 404)
        for key in ['draft', 'unassigned']:
            request('/?post_type=community&p=' + str(community[key]) + '&preview=true', 404)
        request('/?community=ka-milo', 200, community['published'], 'community', canonical=path)
        request('/big-island/guide/fixture-child/', 200, community['guide_rubric'], 'page')
        request('/big-island/guide/fixture-child/fixture-article/', 200, community['guide_article'], 'page')
        request('/community/', 404)
    elif phase == 'moved':
        path = '/big-island/waikoloa-beach-resort/ka-milo/'
        request(path, 200, community['published'], 'community', canonical=path)
        request('/big-island/mauna-lani/ka-milo/', 404)
        request('/big-island/mauna-lani/ka-milo', 404)
        request('/?community=ka-milo', 200, community['published'], 'community', canonical=path)
    elif phase == 'configured':
        path = '/island-test/waikoloa-beach-resort/ka-milo/'
        request(path, 200, community['published'], 'community', canonical=path)
        request('/big-island/waikoloa-beach-resort/ka-milo/', 404)
        request('/island-test/mauna-lani/ka-milo/', 404)
        request('/island-test/guide/fixture-child/', 404)
    elif phase == 'restored':
        path = '/big-island/mauna-lani/ka-milo/'
        request(path, 200, community['published'], 'community', canonical=path)
        request('/big-island/waikoloa-beach-resort/ka-milo/', 404)
        request('/island-test/waikoloa-beach-resort/ka-milo/', 404)
Path(sys.argv[1]).with_name('area-http-' + phase + '-report.json').write_text(json.dumps(report, indent=2))
assert not errors, errors
print(f'PASS: {len(report)} Area/Community HTTP checks ({phase}).')
