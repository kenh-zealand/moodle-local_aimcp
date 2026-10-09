# Indsættelse og kontrol i Moodle

## Afklar integrationen

Skeln mellem Moodles native `mod_h5pactivity` med Indholdsbank/Content bank og det særskilte `mod_hvp`-plugin. Brug de funktioner, som den konkrete installation understøtter; antag ikke, at editor, karakterregistrering og API'er er ens.

Når forsøgsrapport og karakterbog er en del af bestillingen, skal der som udgangspunkt oprettes en native H5P-aktivitet og kontrolleres relevante forsøgs-, karakter- og gennemførelsesindstillinger. Indlejring i en side eller et tekstfelt skal ikke fremstilles som tilsvarende karakterregistrering. Brug indlejring, når det passer til en uformel øvelse, og beskriv registreringsniveauet korrekt.

## Vælg den tilgængelige vej

- **Browser:** Åbn den autoriserede indholdsbank, opret eller upload indholdet, og tilføj det til den angivne sektion. Se, om Moodle kopierer indholdet eller opretter et alias/link; vælg efter behovet for senere fælles opdateringer. Genåbn den færdige aktivitet.
- **MCP:** Undersøg de tilgængelige funktionsskemaer. `local_aimcp` fra version 1.3.0 tilbyder H5P-værktøjerne beskrevet i [mcp.md](mcp.md); brug dem, når de faktisk er udstillet af forbindelsen. `core_files_upload` lægger filer i brugerens kladdeområde og opretter ikke alene en H5P-aktivitet. På ældre installationer må manglende endpoints ikke antages at findes.
- **SSH/CLI, når brugeren har autoriseret serverarbejde:** Brug eksisterende SSH-profil og hold tokens og private nøgler ude af output. Inspicér installationens version, H5P-integration og native API'er før kode skrives. Opret gennem dokumenterede Moodle-API'er med passende bruger og rettighedskontrol; indsæt ikke aktivitets- og biblioteksposter direkte i databasen. Backup ved ændring af eksisterende indhold og fjern midlertidige scripts bagefter. Ændr ikke pluginfiler eller servicekonfiguration for at omgå en manglende indholdsoperation uden en særskilt bestilling.

Hvis installation ikke kan udføres med den tilgængelige adgang, lever den færdige lokale pakke og de præcise trin i den observerede integration. Angiv indsættelse som udestående.

## Verificér den rigtige destination

Kontrollér kursus, sektion, titel, synlighed, fil og faktisk interaktion. Test rette/forkerte svar og afslutning. Ved resultatregistrering verificeres en testbrugers forsøg og karakter samt den valgte gennemførelsesregel. Brug ikke en rigtig elevs konto eller nulstil eksisterende elevforsøg som del af kontrollen.

Kildematerialets ophav, licens og mediebrug skal følge med, når indholdet genbruges. H5P-øvelser med svar i klienten skal ikke fremstilles som en sikret eksamensform.

## Kilder

- [Moodles native H5P-aktivitet](https://docs.moodle.org/502/en/mod/h5pactivity/mod)
- [Moodles filupload og kladdeområder](https://moodledev.io/docs/5.0/apis/subsystems/external/files)
