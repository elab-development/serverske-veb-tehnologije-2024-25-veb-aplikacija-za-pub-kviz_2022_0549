Funkcionalnosti

Auth: registracija, prijava, odjava (Sanctum token)

Seasons: javni prikaz; admin može kreirati/menjati/brisati

Events: listanje sa pretragom, filtrima, sortiranjem i paginacijom; admin kreira/menja/briše

Boards:

/events/{event}/board – tabela poretka za jedan događaj (ime tima, poeni, rank)

/seasons/{season}/board – TOTAL tabela sezone + tabela za svaki događaj u sezoni

Participation:

GET: admin vidi sva učešća; team vidi samo svoja

POST: admin ili team (poeni/rank inicijalno 0)

PUT: samo admin i samo polja total_points i rank

DELETE: admin i team (svoje)

Javni API: /trivia (samo admin) – dohvat pitanja sa Open Trivia DB prema upitu (količina, težina, tip, kategorija)