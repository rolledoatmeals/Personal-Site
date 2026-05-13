HOST ?= 127.0.0.1
PORT ?= 8000
DOCROOT ?= public

.PHONY: run

run:
	php -S $(HOST):$(PORT) -t $(DOCROOT)