"""Inspect and rebuild H5P exports; structural checks do not replace H5P validation."""
import argparse
import json
import stat
import sys
import zipfile
from pathlib import Path, PurePosixPath

MAX_TOTAL = 256 * 1024 * 1024
MAX_ENTRIES = 10000
MEDIA_EXTENSIONS = {'.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.mp3',
                    '.mp4', '.wav', '.ogg', '.ogv', '.webm', '.vtt', '.srt'}


def safe_name(name):
    path = PurePosixPath(name)
    if not name or '\\' in name or path.is_absolute() or '..' in path.parts or ':' in name:
        raise ValueError(f'Unsafe archive path: {name}')
    return path.as_posix()


def read_package(path):
    result = {}
    with zipfile.ZipFile(path) as archive:
        entries = archive.infolist()
        if len(entries) > MAX_ENTRIES or sum(item.file_size for item in entries) > MAX_TOTAL:
            raise ValueError('Package exceeds structural-check limits')
        seen = set()
        for item in entries:
            name = safe_name(item.filename)
            if name in seen:
                raise ValueError(f'Duplicate archive entry: {name}')
            seen.add(name)
            if stat.S_ISLNK(item.external_attr >> 16):
                raise ValueError('Symbolic links are not supported')
            if not item.is_dir():
                result[name] = archive.read(item)
    return result


def json_object(data, label):
    value = json.loads(data)
    if not isinstance(value, dict):
        raise ValueError(f'{label} must be a JSON object')
    return value


def version_number(value):
    if type(value) is int and value >= 0:
        return value
    if isinstance(value, str) and value.isascii() and value.isdecimal():
        return int(value)
    raise ValueError('Invalid library version')


def validate(files):
    for required in ('h5p.json', 'content/content.json'):
        if required not in files:
            raise ValueError(f'Missing {required} at the expected archive path')
    manifest = json_object(files['h5p.json'], 'h5p.json')
    content = json_object(files['content/content.json'], 'content.json')
    for key in ('title', 'mainLibrary'):
        if not isinstance(manifest.get(key), str) or not manifest[key].strip():
            raise ValueError(f'Missing or invalid manifest field: {key}')
    dependencies = manifest.get('preloadedDependencies')
    if not isinstance(dependencies, list) or not dependencies:
        raise ValueError('Missing preloadedDependencies')
    main = [dep for dep in dependencies if isinstance(dep, dict)
            and dep.get('machineName') == manifest['mainLibrary']]
    if len(main) != 1:
        raise ValueError('mainLibrary must appear exactly once in preloadedDependencies')
    missing_libraries = []
    for kind in ('preloadedDependencies', 'dynamicDependencies', 'editorDependencies'):
        deps = manifest.get(kind, [])
        if not isinstance(deps, list):
            raise ValueError(f'{kind} must be an array')
        for dep in deps:
            if not isinstance(dep, dict) or not isinstance(dep.get('machineName'), str):
                raise ValueError('Invalid library dependency')
            for version in ('majorVersion', 'minorVersion'):
                version_number(dep.get(version))
            name = dep['machineName']
            library_path = safe_name(f"{name}-{dep['majorVersion']}.{dep['minorVersion']}/library.json")
            if library_path in files:
                library = json_object(files[library_path], library_path)
                if library.get('machineName') != name or any(
                    version_number(library.get(key)) != version_number(dep[key])
                    for key in ('majorVersion', 'minorVersion')):
                    raise ValueError(f'Library metadata mismatch: {library_path}')
            else:
                missing_libraries.append(library_path.split('/')[0])

    def check_media(value):
        if isinstance(value, dict):
            path = value.get('path')
            if isinstance(path, str) and path and not path.startswith(('https://', 'http://')):
                path = safe_name(path)
                if 'content/' + path not in files:
                    raise ValueError(f'Missing content media: {path}')
            for child in value.values():
                check_media(child)
        elif isinstance(value, list):
            for child in value:
                check_media(child)
    check_media(content)
    return {'title': manifest['title'], 'main_library': manifest['mainLibrary'],
            'dependencies': dependencies, 'content_keys': list(content),
            'file_count': len(files), 'libraries_needed_from_target': sorted(set(missing_libraries)),
            'validation': 'structure only; H5P semantics and Moodle runtime are not tested'}


def build(args):
    source = Path(args.template)
    output = Path(args.output)
    if output.exists() or output.resolve() == source.resolve():
        raise ValueError('Output must be a new file; existing files are never overwritten')
    if output.suffix.lower() != '.h5p':
        raise ValueError('Output must have the .h5p extension')
    files = read_package(source)
    validate(files)
    content = json_object(Path(args.content).read_text(encoding='utf-8-sig'), 'new content')
    manifest = json_object(files['h5p.json'], 'h5p.json')
    if args.title:
        manifest['title'] = args.title
    if args.language:
        manifest['language'] = args.language
    for name, value in [('h5p.json', manifest), ('content/content.json', content)]:
        files[name] = (json.dumps(value, ensure_ascii=False, indent=2) + '\n').encode('utf-8')
    if args.assets:
        root = Path(args.assets)
        if not root.is_dir():
            raise ValueError('Assets must be an existing directory')
        for asset in root.rglob('*'):
            if asset.is_symlink():
                raise ValueError('Asset symbolic links are not supported')
            if asset.is_file():
                if asset.suffix.lower() not in MEDIA_EXTENSIONS:
                    raise ValueError(f'Unsupported media extension: {asset.name}')
                name = safe_name('content/' + asset.relative_to(root).as_posix())
                files[name] = asset.read_bytes()
    if sum(map(len, files.values())) > MAX_TOTAL:
        raise ValueError('Result exceeds package size limit')
    report = validate(files)
    output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(output, 'x', zipfile.ZIP_DEFLATED) as archive:
        for name, data in sorted(files.items()):
            archive.writestr(name, data)
    report['output'] = str(output.resolve())
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest='command', required=True)
    inspect = sub.add_parser('inspect')
    inspect.add_argument('package')
    create = sub.add_parser('build')
    create.add_argument('template')
    create.add_argument('content')
    create.add_argument('output')
    create.add_argument('--title')
    create.add_argument('--language')
    create.add_argument('--assets')
    args = parser.parse_args()
    try:
        report = validate(read_package(args.package)) if args.command == 'inspect' else build(args)
        print(json.dumps(report, ensure_ascii=False, indent=2))
    except (ValueError, OSError, zipfile.BadZipFile, KeyError) as error:
        print(f'ERROR: {error}', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
