.DEFAULT_GOAL := help

.PHONY: help doctor bootstrap up down reset install migrate seed lint test test-smoke e2e status

help:
	@bin/dev help

doctor:
	@bin/dev doctor

bootstrap:
	@bin/dev bootstrap

up:
	@bin/dev up

down:
	@bin/dev down

reset:
	@bin/dev reset

install:
	@bin/dev install

migrate:
	@bin/dev migrate

seed:
	@bin/dev seed

lint:
	@bin/dev lint

test:
	@bin/dev test

test-smoke:
	@bin/dev smoke

e2e:
	@bin/dev e2e

status:
	@bin/dev status
