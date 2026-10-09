# Arbejde med .h5p-pakker

En `.h5p`-fil er et ZIP-arkiv med `h5p.json` i roden og aktivitetens parametre i `content/content.json`. Biblioteker og medier følger pakkens afhængigheder og eksportindstillinger. Bibliotekets `semantics.json` beskriver parametrene, også for indlejrede spørgsmål. Brug en skabelon til samme hovedbibliotek og samme major/minor-version som målmiljøet.

## Praktisk arbejdsgang

1. Eksportér en fungerende aktivitet med relevante underaktiviteter, eller hent en officiel eksempelpakke. Inspicér titel, hovedbibliotek, afhængigheder og medier:

   `python scripts/h5p_package.py inspect skabelon.h5p`

2. Læs `content/content.json` og relevante `semantics.json`/`library.json` fra pakken eller de installerede biblioteker. Feltnavne, nesting, HTML-understøttelse og obligatoriske felter afhænger af typen. Hvis pakken ikke indeholder biblioteker, skal semantikken hentes separat; gæt den ikke.
3. Skriv en ny JSON-fil med indholdet. Bevar nødvendige adfærdsindstillinger og brug formatets rigtige svar- og feedbackfelter. Generér nye `subContentId`-UUID'er til nye underaktiviteter, når biblioteket bruger dem. Bevar ikke identiske UUID'er mellem flere nye underaktiviteter.
4. Genpak med bevarede biblioteker og ny titel:

   `python scripts/h5p_package.py build skabelon.h5p nyt-indhold.json resultat.h5p --title "Ny aktivitet" --language da`

   Tilføj kun medier, der skal bruges, via `--assets mappe`. Filernes placering i mappen svarer til placeringen under `content/`, fx `images/figur.png`. Brug relative mediestier efter den konkrete types semantik. Scriptet tilføjer ikke eksterne billeder eller ændrer afhængigheder automatisk.
5. Kontrollér pakken og importér den i Moodle/den relevante H5P-runtime. Scriptet kontrollerer ZIP-stier, JSON, hovedbibliotek, afhængighedsmetadata og mediereferencer; det validerer ikke alle bibliotekers semantik eller interaktiv adfærd.

Tilføj ikke vilkårlige CSS-, JavaScript- eller PHP-filer for at ændre udseendet. Manglende eller nye bibliotekstyper skal håndteres med en tilsvarende kompatibel skabelon eller en eksplicit bibliotekinstallation, ikke med opdigtede metadata. Biblioteksinstallation er en særskilt adminhandling.

## Kilder

- [H5P-pakkespecifikation](https://h5p.org/node/2463)
- [h5p.json-definition](https://h5p.org/documentation/developers/json-file-definitions)
- [Semantik for biblioteker](https://h5p.org/semantics)
