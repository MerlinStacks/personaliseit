#!/usr/bin/env bash

set -euo pipefail

./node_modules/.bin/wp-scripts plugin-zip

# wp-scripts honours the explicit package files list, but nested Composer
# packages can still contribute their own development fixtures. Remove those
# after archiving so the installable artifact contains runtime code only.
zip -d overcustomise.zip \
	'overcustomise/vendor/tecnickcom/*/.github/*' \
	'overcustomise/vendor/tecnickcom/*/doc/*' \
	'overcustomise/vendor/tecnickcom/*/docs/*' \
	'overcustomise/vendor/tecnickcom/*/example/*' \
	'overcustomise/vendor/tecnickcom/*/examples/*' \
	'overcustomise/vendor/tecnickcom/*/test/*' \
	'overcustomise/vendor/tecnickcom/*/tests/*' \
	'overcustomise/vendor/tecnickcom/*/CODE_OF_CONDUCT.md' \
	'overcustomise/vendor/tecnickcom/*/CONTRIBUTING.md' \
	'overcustomise/vendor/tecnickcom/*/SECURITY.md' \
	'overcustomise/vendor/tecnickcom/*/mago.*.toml' \
	'overcustomise/vendor/tecnickcom/*/phpunit.xml.dist' \
	'overcustomise/vendor/tecnickcom/*/resources/phpmd/*' \
	>/dev/null
