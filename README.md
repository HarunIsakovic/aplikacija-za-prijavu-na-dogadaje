1. Preuzimanje projekta

Klonirati repozitorij:
git clone https://github.com/HarunIsakovic/aplikacija-za-prijavu-na-dogadaje.git

Ući u direktorij projekta:
cd aplikacija-za-prijavu-na-dogadaje

2. Instalacija zavisnosti

Instalirati PHP zavisnosti:
composer install

Instalirati frontend zavisnosti:
npm install

3. Podešavanje okruženja

Kreirati .env datoteku na osnovu postojeće .env.example datoteke.

Za Windows PowerShell:
Copy-Item .env.example .env

Generisati aplikacijski ključ:
php artisan key:generate

4. Podešavanje baze podataka

U MySQL-u kreirati novu bazu podataka, na primjer:
CREATE DATABASE aplikacija_dogadaji;

Zatim u .env datoteci podesiti podatke za povezivanje s bazom:

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aplikacija_dogadaji
DB_USERNAME=root
DB_PASSWORD=

Vrijednosti korisničkog imena i sifre prilagoditi vlastitoj MySQL instalaciji.

5. Migracije i demo administratorski račun

Pokrenuti migracije i automatski kreirati demo administratorski račun:
php artisan migrate --seed

Ako su migracije već izvršene, a potrebno je samo kreirati demo administratorski račun, koristiti:
php artisan db:seed

Demo administratorski pristupni podaci
Email: admin@example.com
Šifra: Admin12345!

Ovi podaci namijenjeni su za demo i lokalno testiranje aplikacije.

Napomena: Prije korištenja u produkcijskom okruženju potrebno je promijeniti administratorsku sifru i prilagoditi način kreiranja administratorskog računa.

6. Pokretanje aplikacije

Za pokretanje aplikacije potrebno je otvoriti dva terminala.

Terminal 1 – Laravel backend:
php artisan serve

Laravel server će biti dostupan na:
http://127.0.0.1:8000

Terminal 2 – React frontend:
npm run dev

Ova komanda pokreće Vite razvojni server koji omogućava rad frontend dijela aplikacije.

Nakon pokretanja oba servera, otvoriti aplikaciju u pregledniku:

http://127.0.0.1:8000

7. Pokretanje testova

Za pokretanje automatskih testova:
php artisan test

8. Produkcijska frontend verzija

Za kreiranje optimizovane frontend verzije:
npm run build
