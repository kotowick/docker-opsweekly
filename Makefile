# Generate tarball with new build of param_api
#

all: build push

build:
	@echo Building $(IMAGE):latest
	@docker build -t $(IMAGE):latest .

push:
	@echo Pushing $(IMAGE):latest
	@docker push $(IMAGE):latest

.PHONY: all build push
