default:
    @just --list

start:
    docker-compose up -d

stop:
    docker-compose down

build:
    docker-compose build
    docker-compose up -d

shell:
    docker exec -it app_ooo_php bash
