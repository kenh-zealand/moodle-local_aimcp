---
name: moodle-h5p-design
description: Opret, tilpas og design H5P-aktiviteter til Moodle med faglige opgaver, forklarende feedback, kompatible .h5p-pakker og kontrol af den indsatte aktivitet. Brug ved ønsker om H5P i Moodle, herunder interaktiv video, spørgsmål, dialogkort, drag-and-drop og interaktive bøger.
---

# H5P til Moodle

Omsæt fagligt materiale til en fungerende H5P-aktivitet med klart formål, passende interaktion og feedback. Lever den ønskede aktivitet eller fil, ikke blot en liste over muligheder. Skriv som udgangspunkt på dansk og brug eksisterende kursuskontekst, niveau og design.

## Afklar opgaven uden at standse unødigt

Læs de angivne kilder og relevant kursusindhold. Fastlæg læringsmål, deltagernes niveau, aktivitetens rolle og forventet tidsforbrug. Spørg kun om forhold, der afgør løsningen: fx manglende kilde, destination eller om resultater skal registreres. Ellers vælg en begrundet standard og fortsæt.

Et kursuslink er en destination, når brugeren har bedt om indsættelse; det er ikke i sig selv tilladelse til at ændre serverkonfiguration. En bestilling på en skill eller en lokal pakke giver ikke tilladelse til at redigere et kursus.

## Vælg arbejdsgang efter den faktiske adgang

- **local_aimcp MCP:** Når forbindelsen udstiller H5P-værktøjerne fra version 1.3.0, læs [mcp.md](references/mcp.md). Brug dem til at læse biblioteker og eksisterende aktiviteter, oprette og opdatere native H5P. Undersøg altid de aktuelle værktøjsskemaer; en skill installeret lokalt beviser ikke, at serveren er opgraderet.
- **Moodle-editor til rådighed:** Undersøg Content bank/Indholdsbank og tilgængelige indholdstyper. Opret med den installerede editor og dens biblioteker. Tilpas en eksisterende aktivitet i en kopi, hvis originalen skal bevares.
- **Lokal .h5p-pakke:** Brug en fungerende eksport eller officiel eksempelpakke til den ønskede type som skabelon. Læs [pakker.md](references/pakker.md), før der ændres JSON eller pakkes filer. Brug `scripts/h5p_package.py` til strukturel kontrol og genpakning. Mangler en kompatibel skabelon eller semantik, fremskaf den eller lever et fuldt manuskript med tydelig status; kald ikke manuskriptet en .h5p-fil.
- **Indsættelse i Moodle:** Læs [moodle.md](references/moodle.md). Undersøg tilgængelige værktøjer før valg af uploadvej. Brug kun observerede API'er, editorfunktioner eller Moodles dokumenterede native API'er. Filupload til et kladdeområde er ikke det samme som en oprettet H5P-aktivitet.

Læs [didaktik-design.md](references/didaktik-design.md), når opgaver, feedback og præsentation udformes. Tilpas omfanget til behovet; opret ikke en hel bog til en enkel kontroløvelse.

## Byg indholdet

Knyt hver interaktion til noget, deltageren skal kunne forstå, skelne mellem eller anvende. Skriv klare instruktioner, entydige svar og feedback, som forklarer sammenhængen. Brug plausible fejlsvar fra almindelige misforståelser. Kontrollér faglig korrekthed og kildegrundlag før pakning.

Brug kun egenskaber, som den konkrete H5P-type understøtter. Bevar biblioteksversioner og afhængigheder fra den validerede skabelon. H5P har ikke én universel content.json-struktur. Ændr ikke globalt Moodle-tema, H5P-bibliotekskode eller plugins som følge af en almindelig indholdsbestilling.

## Kontrollér og aflever

Test et rigtigt og et forkert svar, feedback, genforsøg og afslutning. Se aktiviteten ved normal og smal bredde, og kontrollér tastaturbrug, alternativ tekst og eventuelle undertekster. Hvis resultater skal registreres, test den native H5P-aktivitet med en autoriseret testbruger og verificér forsøgsrapport, karakterbog og gennemførelse hver for sig; en admin-preview beviser ikke elevregistrering.

En pakke, der er strukturelt kontrolleret, er ikke nødvendigvis valideret mod bibliotekernes semantik eller afprøvet i Moodle. Hold disse statusser adskilt. Ret fejl, før aktiviteten betegnes som færdig; hvis adgang blokerer kontrollen, lever det færdige materiale og angiv præcis, hvad der mangler.

Aflever et link til aktiviteten eller en faktisk .h5p-fil og kort angiv indholdstype, formål og kontrolleret status. Oplys relevante begrænsninger, fx manglende elevtest. Gem ikke adgangskoder eller tokens i skillen, pakkerne eller leveringsnoter.
