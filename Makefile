HOST ?= 127.0.0.1
PORT ?= 8000
DOCROOT ?= public

.PHONY: run

run:
	php -S $(HOST):$(PORT) -t $(DOCROOT)

.PHONY: optimize audit

optimize:
	@echo "No-op optimize: generate webp (install cwebp) or use your image toolchain"

audit:
	@./scripts/audit.sh

.PHONY: build

build:
	@./scripts/build.sh