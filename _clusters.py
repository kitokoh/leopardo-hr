
import xml.etree.ElementTree as ET, re, collections
t = ET.parse('_a3/test-results/feature.xml')
fails = []
for tc in t.iter('testcase'):
    node = tc.find('failure')
    if node is None:
        node = tc.find('error')
    if node is None:
        continue
    msg = (node.get('message') or '')[:260]
    txt = (node.text or '')
    frames = re.findall(r'(api/(?:app|tests)/[^:\s]+):(\d+)', txt)
    where = frames[0] if frames else ('?', '?')
    fails.append((tc.get('classname',''), tc.get('name',''), msg.strip(), where))
print('echecs:', len(fails))
def norm(m):
    return re.sub(r'[0-9a-f]{8}-[0-9a-f-]{27,}', 'UUID', re.sub(r'\d+', 'N', m))[:100]
groups = collections.Counter((f[3][0], f[3][1], norm(f[2])) for f in fails)
print()
print('== clusters (fichier:ligne + message normalise) ==')
for (path, line, msg), n in groups.most_common(10):
    print('%2d x %s:%s | %s' % (n, path, line, msg))
mod = collections.Counter(f[0].split('.')[2] if len(f[0].split('.')) > 2 else 'Racine' for f in fails)
print()
print('== par module ==', mod.most_common(10))
print()
print('== fichiers de test touches ==')
for (cl, nm, m, w), n in collections.Counter((f[0], '', f[2], f[3]) for f in fails).most_common(6):
    print('%2d x %s' % (n, cl.split('.')[-1]))

