build:
	@docker build -t imega/tester --rm .

composer:
	@docker run --rm \
		-v $(CURDIR):/data \
		-v $$HOME/.composer/cache:/cache \
		imega/composer install \
		--ignore-platform-reqs --no-interaction $(COMPOSER_FLAGS)

get_containers:
	$(eval CONTAINERS := $(subst build/containers/,,$(shell find build/containers -type f)))

stop: get_containers
	@-docker stop $(CONTAINERS)

clean: stop
	@-docker rm -fv $(CONTAINERS)
	@-docker run --rm \
		-v $(CURDIR):/data \
		-w /data \
		alpine:3.4 rm -rf ./build

build/containers/mock_rabbit_server:
	@mkdir -p $(shell dirname $@)
	@docker run -d --hostname my-rabbit -p 15672:15672 --name mock_rabbit_server rabbitmq:3-management
	@touch $@

test: composer build/containers/mock_rabbit_server
	@docker run --rm \
		--link mock_rabbit_server:rabbit_host \
		-v $(CURDIR):/data \
		imega/tester vendor/bin/phpunit

.PHONY: build
