setup:
	mkdir -p _docker/db/testdb

start: setup
	docker-compose up -d

cli:
	docker-compose exec php-cli bash