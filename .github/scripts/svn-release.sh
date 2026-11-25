#!/usr/bin/env bash
set -euo pipefail

fail() {
	echo "ERROR: $*" >&2
	exit 1
}

: "${PLUGIN_SLUG:?PLUGIN_SLUG is required}"
: "${VERSION:?VERSION is required}"
: "${BUNDLE:?BUNDLE is required}"
: "${DRY_RUN:?DRY_RUN must be true or false}"

PROJECT_ROOT="$(realpath "${GITHUB_WORKSPACE:-$(pwd)}")"
BUNDLE="$(realpath "$BUNDLE")"
SVN_URL="${SVN_URL:-https://plugins.svn.wordpress.org/${PLUGIN_SLUG}/}"
SVN_URL="${SVN_URL%/}/"
PREVIEW_DIR="${SVN_PREVIEW_DIR:-${PROJECT_ROOT}/deploy/svn-preview}"
PREVIEW_DIR="$(realpath -m "$PREVIEW_DIR")"
RELEASE_COMMIT="${RELEASE_COMMIT:-${GITHUB_SHA:-}}"
PACKAGE_SHA256="${PACKAGE_SHA256:-}"
WORKFLOW_RUN_URL="${WORKFLOW_RUN_URL:-${GITHUB_RUN_URL:-}}"
RELEASE_URL="${RELEASE_URL:-}"
TEMP_FILES=()

cleanup() {
	if [[ ${#TEMP_FILES[@]} -gt 0 ]]; then
		rm -f "${TEMP_FILES[@]}"
	fi
}
trap cleanup EXIT

[[ "$PLUGIN_SLUG" =~ ^[a-z0-9-]+$ ]] || fail "Invalid plugin slug: $PLUGIN_SLUG"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+(\.[0-9]+)*$ ]] || fail "Invalid release version: $VERSION"
[[ "$DRY_RUN" == 'true' || "$DRY_RUN" == 'false' ]] || fail "DRY_RUN must be true or false"
[[ -f "$BUNDLE/${PLUGIN_SLUG}.php" ]] || fail "Plugin bundle not found: $BUNDLE"
[[ -f "${PROJECT_ROOT}/.github/scripts/validate-release.php" ]] \
	|| fail "Release validator not found in PROJECT_ROOT."
[[ "$RELEASE_COMMIT" =~ ^[0-9a-f]{40}$ ]] || fail "RELEASE_COMMIT must be a full Git commit SHA."
[[ "$PACKAGE_SHA256" =~ ^[0-9a-f]{64}$ ]] || fail "PACKAGE_SHA256 must be a SHA-256 digest."
[[ "$PREVIEW_DIR" != '/' && "$PREVIEW_DIR" != "$PROJECT_ROOT" && "$PREVIEW_DIR" != "$BUNDLE" ]] \
	|| fail "Unsafe SVN_PREVIEW_DIR: $PREVIEW_DIR"
if [[ -n "$WORKFLOW_RUN_URL" && "$WORKFLOW_RUN_URL" != https://github.com/* ]]; then
	fail "WORKFLOW_RUN_URL must be an HTTPS GitHub Actions URL."
fi
if [[ -n "$RELEASE_URL" && "$RELEASE_URL" != https://github.com/* ]]; then
	fail "RELEASE_URL must be an HTTPS GitHub URL."
fi

if [[ "$DRY_RUN" == 'true' && ( -n "${SVN_USERNAME:-}" || -n "${SVN_PASSWORD:-}" ) ]]; then
	fail "Dry runs must not receive SVN credentials."
fi

auth_args=()
if [[ -n "${SVN_USERNAME:-}" && -n "${SVN_PASSWORD:-}" ]]; then
	auth_args=( --username "$SVN_USERNAME" --password "$SVN_PASSWORD" )
elif [[ -n "${SVN_USERNAME:-}" || -n "${SVN_PASSWORD:-}" ]]; then
	fail "SVN_USERNAME and SVN_PASSWORD must be supplied together."
elif [[ "$DRY_RUN" == 'false' && "$SVN_URL" == https://* ]]; then
	fail "SVN credentials are required to publish to WordPress.org."
fi

if [[ -n "${SVN_WORK_DIR:-}" ]]; then
	WORK_DIR="$SVN_WORK_DIR"
else
	WORK_DIR="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${PLUGIN_SLUG}-svn-${VERSION}.XXXXXX")"
fi

mkdir -p "$WORK_DIR"
WORK_DIR="$(realpath "$WORK_DIR")"
if [[ -n "$(find "$WORK_DIR" -mindepth 1 -print -quit)" ]]; then
	fail "SVN working directory is not empty: $WORK_DIR"
fi

rm -rf "$PREVIEW_DIR"
mkdir -p "$PREVIEW_DIR"

write_provenance() {
	local revision="${1:-}"
	python3 - "$PREVIEW_DIR/release-provenance.json" "$revision" <<'PY'
import json
import os
import sys

keys = (
    "PLUGIN_SLUG",
    "VERSION",
    "DRY_RUN",
    "RELEASE_COMMIT",
    "PACKAGE_SHA256",
    "WORKFLOW_RUN_URL",
    "GITHUB_RUN_ATTEMPT",
    "RELEASE_URL",
    "SVN_URL",
)
values = {key.lower(): os.environ.get(key, "") for key in keys}
values["svn_revision"] = sys.argv[2]
with open(sys.argv[1], "w", encoding="utf-8") as handle:
    json.dump(values, handle, indent=2, sort_keys=True)
    handle.write("\n")
PY
}

echo "Preparing ${PLUGIN_SLUG} ${VERSION} from ${SVN_URL}"

svn checkout \
	--depth immediates \
	--non-interactive \
	--no-auth-cache \
	"${auth_args[@]}" \
	"$SVN_URL" \
	"$WORK_DIR"

cd "$WORK_DIR"

# A failed listing is not evidence that a tag is absent. Fail closed.
tag_list="$(mktemp)"
TEMP_FILES+=( "$tag_list" )
if ! svn list \
	--xml \
	--non-interactive \
	--no-auth-cache \
	"${auth_args[@]}" \
	"${SVN_URL}tags" > "$tag_list"; then
	fail "Could not list SVN tags; refusing to continue."
fi

set +e
python3 - "$tag_list" "$VERSION" <<'PY'
import sys
import xml.etree.ElementTree as ET

try:
    root = ET.parse(sys.argv[1]).getroot()
except (ET.ParseError, OSError) as exc:
    print(f"Could not parse SVN tag listing: {exc}", file=sys.stderr)
    raise SystemExit(2)

names = {entry.findtext("name", "") for entry in root.findall(".//entry")}
raise SystemExit(10 if sys.argv[2] in names else 0)
PY
tag_status=$?
set -e

case "$tag_status" in
	0) ;;
	10) fail "SVN tag $VERSION already exists; refusing to overwrite it." ;;
	*) fail "Could not interpret the SVN tag listing; refusing to continue." ;;
esac

cp "$tag_list" "$PREVIEW_DIR/svn-tags.xml"

svn update \
	--non-interactive \
	--no-auth-cache \
	"${auth_args[@]}" \
	--set-depth infinity trunk

svn update \
	--non-interactive \
	--no-auth-cache \
	"${auth_args[@]}" \
	--set-depth immediates tags

# Sync only the runtime package. Top-level SVN assets are never touched.
rsync -rc --delete --exclude='.svn/' "$BUNDLE/" trunk/

# Stage additions, then explicitly stage paths deleted by rsync.
svn add --force trunk >/dev/null

status_xml="$(mktemp)"
TEMP_FILES+=( "$status_xml" "${status_xml}.missing" )
svn status --xml trunk > "$status_xml"

python3 - "$status_xml" <<'PY' > "${status_xml}.missing"
import sys
import xml.etree.ElementTree as ET

root = ET.parse(sys.argv[1]).getroot()
paths = sorted(
    {
        entry.get("path")
        for entry in root.findall(".//entry")
        if entry.get("path")
        and entry.find("wc-status") is not None
        and entry.find("wc-status").get("item") == "missing"
    },
    key=lambda value: (value.count("/"), value),
)
selected = []
for path in paths:
    if not any(path == parent or path.startswith(parent + "/") for parent in selected):
        selected.append(path)
for path in selected:
    sys.stdout.buffer.write(path.encode("utf-8") + b"\0")
PY

while IFS= read -r -d '' missing_path; do
	svn delete --force "$missing_path"
done < "${status_xml}.missing"

# The release tag must be an SVN copy of the fully staged trunk.
svn copy trunk "tags/$VERSION"

php "${PROJECT_ROOT}/.github/scripts/validate-release.php" \
	"$VERSION" \
	"$WORK_DIR/trunk" \
	"$PROJECT_ROOT"

diff -qr --exclude='.svn' trunk "tags/$VERSION"

all_status="$(mktemp)"
TEMP_FILES+=( "$all_status" )
svn status --xml > "$all_status"

python3 - "$all_status" "$VERSION" <<'PY'
import sys
import xml.etree.ElementTree as ET

root = ET.parse(sys.argv[1]).getroot()
version = sys.argv[2]
allowed = ("trunk", f"tags/{version}")
blocked = {"missing", "unversioned", "conflicted", "obstructed", "incomplete", "ignored", "external"}
counts = {}

for entry in root.findall(".//entry"):
    path = entry.get("path", "")
    status = entry.find("wc-status")
    item = status.get("item", "normal") if status is not None else "normal"
    counts[item] = counts.get(item, 0) + 1

    if not any(path == prefix or path.startswith(prefix + "/") for prefix in allowed):
        raise SystemExit(f"Unexpected SVN change outside trunk/tags/{version}: {path}")
    if item in blocked:
        raise SystemExit(f"Unsafe SVN status '{item}' for {path}")

if not any(
    entry.get("path") == f"tags/{version}"
    and entry.find("wc-status") is not None
    and entry.find("wc-status").get("item") == "added"
    for entry in root.findall(".//entry")
):
    raise SystemExit(f"Staged SVN copy tags/{version} was not found.")

print("Staged SVN status: " + ", ".join(f"{key}={value}" for key, value in sorted(counts.items())))
PY

svn status > "$PREVIEW_DIR/svn-status.txt"
svn diff > "$PREVIEW_DIR/svn-diff.txt" || true
(
	find trunk -path '*/.svn' -prune -o -type f -print0 \
		| sort -z \
		| xargs -0 -r sha256sum
) > "$PREVIEW_DIR/trunk-manifest.sha256"
write_provenance ''

echo "Staged SVN changes:"
svn status

if [[ "$DRY_RUN" == 'true' ]]; then
	echo "DRY RUN COMPLETE: no SVN commit was performed."
	exit 0
fi

commit_message="${PLUGIN_SLUG}: Release ${VERSION} from GitHub"
commit_message+=$'\n'"Commit: ${RELEASE_COMMIT}"
commit_message+=$'\n'"Artifact SHA-256: ${PACKAGE_SHA256}"
if [[ -n "$WORKFLOW_RUN_URL" ]]; then
	commit_message+=$'\n'"Workflow: ${WORKFLOW_RUN_URL}"
	if [[ -n "${GITHUB_RUN_ATTEMPT:-}" ]]; then
		commit_message+=" (attempt ${GITHUB_RUN_ATTEMPT})"
	fi
fi
if [[ -n "$RELEASE_URL" ]]; then
	commit_message+=$'\n'"Release/tag: ${RELEASE_URL}"
fi

svn commit \
	--non-interactive \
	--no-auth-cache \
	"${auth_args[@]}" \
	-m "$commit_message"

# Read the actual commit revision from the local tags/VERSION working copy.
revision="$(svn info --show-item last-changed-revision "$WORK_DIR/tags/$VERSION")"
[[ "$revision" =~ ^[0-9]+$ ]] || fail "Could not determine the committed SVN revision."
working_revision="$(svn info --show-item revision "$WORK_DIR/tags/$VERSION")"
[[ "$working_revision" == "$revision" ]] \
	|| fail "Local tag revision $working_revision does not match committed revision $revision."
write_provenance "$revision"

verify_remote_package() {
	local remote_path="$1"
	local label="$2"
	local remote_revision
	local export_root
	local export_dir

	remote_revision="$(svn info --show-item revision "${SVN_URL}${remote_path}@${revision}")"
	[[ "$remote_revision" == "$revision" ]] \
		|| fail "Remote ${label} is at revision ${remote_revision}, expected ${revision}."

	export_root="$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/${PLUGIN_SLUG}-verify-${label//\//-}.XXXXXX")"
	export_dir="${export_root}/package"
	if ! svn export \
		--quiet \
		--revision "$revision" \
		--non-interactive \
		--no-auth-cache \
		"${auth_args[@]}" \
		"${SVN_URL}${remote_path}@${revision}" \
		"$export_dir"; then
		rm -rf "$export_root"
		fail "Could not export remote ${label} for verification."
	fi

	if ! diff -qr --exclude='.svn' "$BUNDLE" "$export_dir"; then
		rm -rf "$export_root"
		fail "Remote ${label} differs from the verified package."
	fi
	if ! php "${PROJECT_ROOT}/.github/scripts/validate-release.php" \
		"$VERSION" \
		"$export_dir" \
		"$PROJECT_ROOT"; then
		rm -rf "$export_root"
		fail "Remote ${label} failed release validation."
	fi

	rm -rf "$export_root"
	echo "Verified remote ${label} at SVN revision ${revision}."
}

verify_remote_package trunk trunk
verify_remote_package "tags/${VERSION}" "tag ${VERSION}"

if [[ -n "${GITHUB_OUTPUT:-}" ]]; then
	{
		echo "version=$VERSION"
		echo "revision=$revision"
		echo "commit=$RELEASE_COMMIT"
		echo "package_sha256=$PACKAGE_SHA256"
		if [[ -n "$WORKFLOW_RUN_URL" ]]; then
			echo "workflow_run_url=$WORKFLOW_RUN_URL"
		fi
		if [[ -n "$RELEASE_URL" ]]; then
			echo "release_url=$RELEASE_URL"
		fi
	} >> "$GITHUB_OUTPUT"
fi
echo "Deployed and verified ${PLUGIN_SLUG} ${VERSION} at SVN revision ${revision}."
