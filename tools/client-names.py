#!/usr/bin/env python3
"""Fail when a client or partner name lands in this public repo.

Scout Repo Standard rule 5: Scout-Plugins holds platform code and docs only, no
client names. This check reads the lines a change adds (and the paths of the
files it adds or renames) and fails when one carries a name on the deny list.

The deny list below holds SHA-256 hashes, never the names, so publishing this
file does not publish the list. A match is reported by file, line and entry id
only; the matching text is never printed, so CI logs stay clean too.

  python3 tools/client-names.py --base origin/main     lines added since main
  python3 tools/client-names.py --all                  every tracked file, every line
  python3 tools/client-names.py --which "some text"    entry ids the text matches (local use)
  python3 tools/client-names.py --hash "Some Name"     the line to add for a new name

How text is matched: lowercased, accents and apostrophes dropped, split into
words at anything that is not a letter or digit and at camelCase humps, then
every run of one to five words is joined without spaces and hashed. So
"North Crest", "northcrest" and "NorthCrest" all give the same hash, and a
deny entry is always written as one joined lowercase word.

Intentional mentions (a plugin built for a kind of business may need one) go in
.github/client-names-allow.txt: one path glob per line, optionally followed by
the entry ids it allows (all ids when none are given), with a dated reason in a
comment above it.
"""
import argparse
import fnmatch
import hashlib
import os
import re
import subprocess
import sys
import unicodedata

SALT = 'scout-plugins/client-names/v1:'
MAX_WORDS = 5
ALLOW_FILE = '.github/client-names-allow.txt'

# id -> sha256(SALT + joined lowercase name). Add a line with --hash. Keep the
# ids stable: allow entries refer to them.
DENY = {
    'c01': '5b1ef28973271d096cfecfd6827e88fdf6d8e2b31d0fb09084dd75cca27ef78e',
    'c02': '3561a2044b2d7b14b8620bdb8cbcb93150b8be87eacea12803626de469671aa5',
    'c03': 'aa0a20d4857fd7aca4b9a2e08e6d8f22f1a07211fa50c574e8434a2cd447fae4',
    'c04': '624f62bc38473d5ac58d544d9a33e5948fd1f18f4c234cc16303ff2caf8c02e5',
    'c05': '1bb0956091acab3ac744b30de4cb7923dcf67696919b8a9f31a32755a9c7651b',
    'c06': 'dddd5283cf03602124fc0742c838c91c757b2b7bdabb064d6d62732ab1c417cc',
    'c07': '968c1f7c8e83673e312e70a0b0178360edee27fa632900b04ab5c66a476ba21b',
    'c08': '722b846b073dffa0f27eef690b6db9599c77bbea72a04372d86695908a56c1e9',
    'c09': 'ba567d4eddf67e48b20bdbb8b7428c9b572e661fbb8c6ae22884b4bbf3e1688e',
    'c10': 'f9a6863309d7ce110b27903208aae2431b7698f3e7cc19a0b2c15c1f3969dcb9',
    'c11': 'a4ae144b987e4faba24a71f5d4264d56ebe212d4829496c7e1322a4c36527f2a',
    'c12': '384505414d2c8f5ca116d90d2aea3cbfb5a431f22e12adab4f18f3f0636a706a',
    'c13': '65ffce8c00a15604a3ee05b069d967f729aecf40e3d04e95adacbde512766fba',
    'c14': '7996ec772c9312a1798d8b15086d604e9ede02b6c2029ea731a636d2104b95c4',
    'c15': '082ab78baedb952c0c7443908a748aac7e4515d3fdf9c9a33525d86ef530f6e6',
    'c16': '8fc0d319ce9bf521a9504f3f104b1f6adeb33197df1628aac411bc4647908f3c',
    'c17': 'd009c568f28682747d4b4f1fd75aa3230597bc65ad951a50852f42d300f8dff6',
    'c18': '66f81b33e57a8cb9c7616c513f88afb606e49e8e75eb3cd30437b9d031fc467b',
    'c19': '18ab32704254cebf5abcbbcde45111de270f6d7d4d47e1a21b0570e9948e63d4',
    'c20': '0534ab07c7d31ed6ed374664eddac15d62a148d5e6de4aa7d3529915b9f0e172',
    'c21': '0c40d94e2fda9ff642e897a494f48caa71ca139790f329743e3c889f1f0357cb',
    'c22': '7dacbf8757cf8265010635a4196da3ae9f4104b619ce1e25f22ea0421e4d5c73',
    'c23': '344510ee4a4eeec0a1a36ebad022177ce705b1bb78dd7281556673eb9614258c',
    'c24': 'c35c85148afa98d986014dcccb163473a888eec3a567e9797239099e4a81d0ac',
    'c25': 'a58c0c18b986e778b175b6cecc712fefad2256e53ad3cedd42a363daad5d59e4',
    'c26': 'eef9d641ec16b5c21c8cb64f1241a75a25a3c24aa3691d7b4f7a69e9c0d90959',
    'c27': '3c16a5a07c7c019add2415ef19adc70a3d907a01d03aff39f45c424d4e86882d',
    'c28': 'a70cccf470022e997c439dff1f4c2aab39571f21baece59878d292b21d7e6080',
    'c29': 'fc21513de76371ef1851d4c5884e57c631fdedcd76745b62ebc13bb8e57b8ac1',
    'c30': 'a599dc78253247006f32eff23f199ec9bc65761315222c95448dea854d5c6461',
    'c31': 'a6b037ed650dddeb8e1dc7c6b9e2c6309ce5d384d00c3fe92b80171c19babddb',
    'c32': 'c64e834df9ac9cb4c97ecdac5e2913e90cb334a53f1529bcd3fd46578260f5cc',
    'c33': '1a4ae1abb7f1a48bacea20f83439b5f564a4a4766c9005f7bd7428faf259b5bb',
    'c34': '53f18597b6b602c73533d39eabf07896fd34b8765b9710dc5733bd04193256ec',
    'c35': '7edda9fdde5ca70f203c2bd16043ec70ef3931fe36df97ed9ed1569778fbf35e',
    'c36': 'd9e358f57e3727b3908fb49e409ee77a3ef807ed7f626635c5c6d88832bba0ef',
    'c37': 'b0fa4da86008c50b12a4084e2180cfffab4a2c3b25286e0fbf2617ca87d6a4a9',
    'c38': '4eed19f2453507245043f704378f0d5a82ac1a11b4deee9420eff9292f43effd',
    'c39': '0ba2fcd474d50c11afe9c01c97d3006d167d065889d9e6d49eca3e258508fbe6',
    'c40': 'bd09b273bdf49ef82a56cc3e542d7e0bb0c205534d48cdb222fb3d078c620743',
    'c41': '622f2ba31ba985be23f3282750eb7182bd66f62ddea35818672384dcf4350c90',
    'c42': 'a8e8d57692a82180496f15c357839bd01072ba6aa7c939bb27c419048795c003',
    'c43': 'eefeef6a6c9be8ee5bc8e8a2463d26a321e2c4199990c39a4c6eab55af485b3d',
    'c44': '7c97337515bbc0be1fed2d16e84cb47acea2aea37998bc7ec82de31d2d529841',
    'c45': 'a13ba8ff3313eeae04e3ef5ad54bb3173cdeb1c170e41c66b74815b7567e56ab',
    'c46': '3eac4d6ca85d004d33720f949f954904957d34f9a6d1dbcd0492ef519990122f',
    'c47': 'd5c26d453095e2d0393c2c25f69b97b7df6557ff763a01787dc6443efb6700d7',
    'c48': '876e44c743f6d49712661899b478ebd81e0b8087a42d910ef9c0747ea4cf7395',
}

WORD = re.compile(r'[a-z0-9]+')
HUMP = re.compile(r'[A-Z]+(?=[A-Z][a-z])|[A-Z]?[a-z]+|[A-Z]+|[0-9]+')


def digest(joined):
    return hashlib.sha256((SALT + joined).encode('utf-8')).hexdigest()


def fold(text):
    text = unicodedata.normalize('NFKD', text)
    text = ''.join(c for c in text if not unicodedata.combining(c))
    return re.sub("['’‘`]", '', text)


def words(text):
    """Lowercase words, split at non-alphanumerics and at camelCase humps."""
    out = []
    for raw in re.findall(r'[A-Za-z0-9]+', fold(text)):
        parts = HUMP.findall(raw)
        out.extend(p.lower() for p in (parts or [raw]))
        if len(parts) > 1:
            out.append(None)  # a boundary marker so the whole token is also tried
            out.append(raw.lower())
            out.append(None)
    return out


def matches(text, lookup):
    """Entry ids whose name appears in the text."""
    found = set()
    seq = words(text)
    for i in range(len(seq)):
        if seq[i] is None:
            continue
        joined = ''
        for n in range(MAX_WORDS):
            if i + n >= len(seq) or seq[i + n] is None:
                break
            joined += seq[i + n]
            hit = lookup.get(digest(joined))
            if hit:
                found.add(hit)
    return found


def load_allow(root):
    rules = []
    path = os.path.join(root, ALLOW_FILE)
    if not os.path.isfile(path):
        return rules
    for line in open(path, encoding='utf-8'):
        line = line.split('#', 1)[0].strip()
        if not line:
            continue
        parts = line.split()
        rules.append((parts[0], set(parts[1:])))
    return rules


def allowed(path, entry, rules):
    for glob, ids in rules:
        if fnmatch.fnmatch(path, glob) and (not ids or entry in ids):
            return True
    return False


def git(root, *args):
    return subprocess.run(['git', '-C', root, *args], check=True, capture_output=True, text=True).stdout


def added_lines(root, base):
    """(path, line number, text) for every line added since base, plus new paths."""
    out = []
    for path in git(root, 'diff', '--name-only', '--diff-filter=AR', base + '...HEAD').splitlines():
        out.append((path, 0, path))
    path, num = None, 0
    diff = git(root, 'diff', '-U0', '--no-color', '--no-ext-diff', base + '...HEAD')
    for line in diff.splitlines():
        if line.startswith('+++ '):
            path = line[6:] if line.startswith('+++ b/') else None
        elif line.startswith('@@'):
            m = re.search(r'\+(\d+)', line)
            num = int(m.group(1)) if m else 0
        elif path and line.startswith('+'):
            out.append((path, num, line[1:]))
            num += 1
    return out


def all_lines(root):
    out = []
    for path in git(root, 'ls-files').splitlines():
        out.append((path, 0, path))
        full = os.path.join(root, path)
        try:
            with open(full, encoding='utf-8') as fh:
                for num, line in enumerate(fh, 1):
                    out.append((path, num, line))
        except (UnicodeDecodeError, IsADirectoryError, FileNotFoundError):
            continue
    return out


def main():
    ap = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    ap.add_argument('--base', help='compare against this ref (lines added since it)')
    ap.add_argument('--all', action='store_true', help='scan every tracked file')
    ap.add_argument('--which', help='print the entry ids this text matches')
    ap.add_argument('--hash', help='print a deny-list line for this name')
    args = ap.parse_args()
    root = git(os.getcwd(), 'rev-parse', '--show-toplevel').strip()
    lookup = {h: i for i, h in DENY.items()}

    if args.hash:
        joined = ''.join(WORD.findall(fold(args.hash).lower()))
        print(f"    'cNN': '{digest(joined)}',")
        return 0
    if args.which is not None:
        print(' '.join(sorted(matches(args.which, lookup))) or 'no match')
        return 0
    if not args.all and not args.base:
        ap.error('give --base <ref> or --all')

    rows = all_lines(root) if args.all else added_lines(root, args.base)
    rules = load_allow(root)
    bad = 0
    skipped = 0
    for path, num, text in rows:
        if path == 'tools/client-names.py':
            continue
        for entry in sorted(matches(text, lookup)):
            if allowed(path, entry, rules):
                skipped += 1
                continue
            where = f'file={path},line={num}' if num else f'file={path}'
            what = f'line {num}' if num else 'the file name'
            print(f'::error {where}::{path} {what} carries a client or partner name (deny-list entry {entry}). '
                  f'This repo is public (Scout Repo Standard rule 5): describe the client by type instead, or, for an '
                  f'intentional mention, add the path to {ALLOW_FILE} with a dated reason.')
            bad += 1
    scope = 'every tracked file' if args.all else f'lines added since {args.base}'
    print(f'Checked {len(rows)} lines ({scope}) against {len(DENY)} names: {bad} found, {skipped} allowed.')
    return 1 if bad else 0


if __name__ == '__main__':
    sys.exit(main())
