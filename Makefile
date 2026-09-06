up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f app nginx

artisan:
	docker compose exec app php artisan $(CMD)

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

key:
	docker compose exec app php artisan key:generate --force
