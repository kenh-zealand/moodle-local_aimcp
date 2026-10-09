# H5P gennem local_aimcp

Kræver `local_aimcp` 1.3.0 og Moodles native `mod_h5pactivity`. Funktionerne er registreret i begge MCP-services og som AJAX-funktioner. En skill er klientvejledning; Moodle får først værktøjerne efter pluginopgradering og genindlæsning af forbindelsen.

## Find indholdstype og destination

Læs kursusindhold og brug `local_aimcp_get_h5p_libraries(courseid)` til at finde installerede versioner. `runnable=true` angiver mulige hovedtyper. Brug kun aktiverede versioner. Hent `includesemantics=true` med et præcist `machinename`, når indholdet udformes. Hent også semantik for relevante underaktiviteter. Brug en fungerende eksport eller officiel pakke som skabelon; værktøjerne opfinder ikke indholds-JSON.

`section` er sektionsnummeret fra kursusindholdets `section`-felt, ikke sektionens database-id. `cmid` er kursusmodulets id, ikke aktivitetsinstansens id eller H5P-afspillerens id.

## Opret

Byg en `.h5p` med skillens pakkeværktøj. Kald `local_aimcp_create_h5p` med `courseid`, `section`, `name` og præcis én kilde:

- `packagedata`: ren base64 af filens bytes, uden data-URL-prefix. Dette virker også i den eksterne service, som ikke har generel filupload.
- `draftitemid`: et eksisterende kladdeområde tilhørende den aktuelle bruger med præcis én lokal `.h5p` i roden. På den interne service kan `core_files_upload` bruges; kopier den faktisk returnerede draft-id. Værktøjet bevarer den oprindelige kladde.

Valgfrit: `intro` (HTML), `visible`, `enabletracking`, `grade` og `grademethod`. Standard er en synlig øvelse uden karakter og forsøgsregistrering. Vælg tracking og karakter efter brugerens læringsmål. `grade=0` betyder ingen karakter; 0..1000 point understøttes. Karaktermetoder: 0 manuel, 1 højeste, 2 gennemsnit, 3 sidste, 4 første forsøg. Brug eksisterende `local_aimcp_set_completion`, hvis gennemførelse er ønsket.

Komprimeret pakke: højst 20 MiB eller den lavere kursus-/sitegrænse. Udvidet arkiv: højst 100 MiB og 2000 poster. Bundtede biblioteksfiler fjernes. Manifest og indhold/medier bevares. Alle deklarerede biblioteker skal allerede være installeret og aktiveret i de angivne major/minor-versioner. Manglende bibliotek kræver en særskilt autoriseret admininstallation; forsøg ikke at omgå dette med SSH eller andre filtyper.

Svaret indeholder `cmid`, `instanceid`, `url` og `warnings`. En strukturelt gyldig pakke og Moodles pakkevalidering beviser ikke korrekt interaktion eller elevregistrering. Kontrollér aktiviteten som angivet i SKILL.md.

## Læs og opdater

Kald `local_aimcp_get_h5p(cmid)` før ændring. Svaret indeholder placering, synlighed, indstillinger, manifest, indholds-JSON, SHA1 `contenthash` og en autentificeret `packageurl`. URL'en er ikke offentlig og giver ikke i sig selv downloadadgang. Hent originalpakken med den autoriserede forbindelse og gem backup før udskiftning.

`local_aimcp_update_h5p(cmid, packagedata=...)` eller `draftitemid=...` erstatter pakken efter validering. `name` og `intro` ændres kun, hvis de er angivet; en tom intro rydder beskrivelsen. Aktivitetens id, placering, synlighed, karakter-/tracking-/gennemførelsesindstillinger og eksisterende forsøg bevares. Eksisterende forsøg er historik for det tidligere indhold; de bliver ikke nulstillet eller gjort til forsøg på den nye version. Ved væsentligt ændrede opgaver kan en ny aktivitet derfor være mere passende.

Læs aktiviteten igen og kontrollér indhold, placering og visning. Et usikkert udfald ved oprettelse må ikke efterfølges af blind gentagelse: læs først kursusindholdet og find den eventuelt oprettede aktivitet. Opdatering skal også kontrolleres før genforsøg. Oplys præcist, hvis en kontrol i browseren eller med testbruger mangler.

Begge skriveværktøjer kontrollerer kursusredigering og `moodle/h5p:deploy`; oprettelse kræver også `mod/h5pactivity:addinstance`. Læseværktøjerne kræver kursusredigering og udleverer ikke elevforsøg.
