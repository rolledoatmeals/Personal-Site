HOST ?= 127.0.0.1
PORT ?= 8000
DOCROOT ?= public
HEROKU_APP ?= zach-peronal-site

.PHONY: run optimize audit build heroku-create heroku-deploy heroku-open heroku-verify deploy

run:
	php -S $(HOST):$(PORT) -t $(DOCROOT)

optimize:
	@echo "No-op optimize: generate webp (install cwebp) or use your image toolchain"

audit:
	@./scripts/audit.sh

build:
	@./scripts/build.sh

heroku-create:
	@heroku apps:info --app "$(HEROKU_APP)" >/dev/null 2>&1 || heroku create "$(HEROKU_APP)" --buildpack heroku/php

heroku-deploy:
	@HEROKU_APP="$(HEROKU_APP)" ./scripts/deploy.sh

heroku-open:
	@heroku open --app "$(HEROKU_APP)"

heroku-verify:
	@echo "Latest release:"
	@heroku releases --app "$(HEROKU_APP)" | sed -n '1,6p'
	@echo
	@echo "Live URL: $$(heroku info --app "$(HEROKU_APP)" --shell | awk -F= '/^web_url=/{print $$2}')"

deploy:
	@$(MAKE) heroku-create HEROKU_APP="$(HEROKU_APP)"
	@$(MAKE) heroku-deploy HEROKU_APP="$(HEROKU_APP)"
	@$(MAKE) heroku-verify HEROKU_APP="$(HEROKU_APP)"