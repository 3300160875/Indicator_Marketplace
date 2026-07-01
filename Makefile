.DEFAULT_GOAL := help

.PHONY: help doctor bootstrap up down reset install migrate seed lint test test-unit test-integration test-concurrency test-smoke e2e perf-baseline perf-compare status ci

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

test-unit:
	@bin/dev test-unit "$(MODULE)"

test-integration:
	@bin/dev test-integration "$(TEST)"

test-concurrency:
	@bin/dev test-concurrency "$(TEST)"

test-smoke:
	@bin/dev smoke

e2e:
	@bin/dev e2e

perf-baseline:
	@bin/dev perf-baseline

perf-compare:
	@bin/dev perf-compare

status:
	@bin/dev status

ci:
	@bin/dev ci
