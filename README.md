# Aplikacija za prijavu na događaje

## Potrebni preduslovi

Prije pokretanja projekta potrebno je instalirati:

- PHP 8.5
- Composer
- Node.js i npm
- MySQL

## 1. Preuzimanje projekta

Klonirati repozitorij:

```bash
git clone https://github.com/HarunIsakovic/aplikacija-za-prijavu-na-dogadaje.git
```

Ući u direktorij projekta:

```bash
cd aplikacija-za-prijavu-na-dogadaje
```

## 2. Instalacija zavisnosti

Instalirati PHP zavisnosti:

```bash
composer install
```

Instalirati frontend zavisnosti:

```bash
npm install
```

## 3. Podešavanje okruženja

Kreirati `.env` datoteku na osnovu postojeće `.env.example` datoteke.

Za Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Generisati aplikacijski ključ:

```bash
php artisan key:generate
```

## 4. Podešavanje baze podataka

U MySQL-u kreirati novu bazu podataka, na primjer:

```sql
CREATE DATABASE aplikacija_dogadaji;
```

Zatim u `.env` datoteci podesiti podatke za povezivanje s bazom:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aplikacija_dogadaji
DB_USERNAME=root
DB_PASSWORD=
```

Vrijednosti korisničkog imena i šifre prilagoditi vlastitoj MySQL instalaciji.

## 5. Migracije i demo administratorski račun

Pokrenuti migracije i automatski kreirati demo administratorski račun:

```bash
php artisan migrate --seed
```

Ako su migracije već izvršene, a potrebno je samo kreirati demo administratorski račun, koristiti:

```bash
php artisan db:seed
```

### Demo administratorski pristupni podaci

- **Email:** admin@example.com
- **Šifra:** Admin12345!

Ovi podaci namijenjeni su za demo i lokalno testiranje aplikacije.

**Napomena:** Prije korištenja u produkcijskom okruženju potrebno je promijeniti administratorsku šifru i prilagoditi način kreiranja administratorskog računa.

## 6. Pokretanje aplikacije

Za pokretanje aplikacije potrebno je otvoriti dva terminala.

**Terminal 1 – Laravel backend:**

```bash
php artisan serve
```

Laravel server će biti dostupan na:

http://127.0.0.1:8000

**Terminal 2 – React frontend:**

```bash
npm run dev
```

Ova komanda pokreće Vite razvojni server koji omogućava rad frontend dijela aplikacije. Terminal treba ostati otvoren dok se aplikacija koristi.

Nakon pokretanja oba servera, otvoriti aplikaciju u pregledniku:

http://127.0.0.1:8000

## 7. Pokretanje testova

Za pokretanje automatskih testova:

```bash
php artisan test
```

## 8. Produkcijska frontend verzija

Za kreiranje optimizovane frontend verzije:

```bash
npm run build
```
