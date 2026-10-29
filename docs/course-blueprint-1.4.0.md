# local_aimcp 1.4.0: samlet kursusoprettelse (forslag)

Denne ændring tilføjer to MCP-funktioner til eksisterende `local_aimcp`:

- `local_aimcp_validate_course_blueprint(blueprintjson)`: læs og kontrollér JSON, kategori, rettigheder og format uden at oprette noget.
- `local_aimcp_create_complete_course(blueprintjson, expectedhash?, includeids?)`: opret hele kurset gennem de eksisterende `create_*`-funktioner, og returnér et kort svar.

De 26 eksisterende specialfunktioner bliver ikke ændret.

## Eksempel på JSON-kontrakt

```json
{
  "schema_version": "1.0",
  "course": {
    "fullname": "Quiz i undervisningen",
    "shortname": "quiz-i-undervisningen-01",
    "categoryid": 0,
    "format": "grid",
    "visible": false,
    "enablecompletion": true,
    "summary": "<p>Et praksisnært forløb.</p>"
  },
  "sections": [
    {
      "id": "start",
      "name": "Start her",
      "summary": "<p>Hvad skal vi lære?</p>",
      "image": {
        "svg": "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 1140 600\"><rect width=\"1140\" height=\"600\" fill=\"#edf4fc\"/></svg>",
        "alttext": "Lys blå illustration"
      },
      "activities": [
        {
          "id": "intro",
          "type": "page",
          "name": "Hvorfor bruge quiz?",
          "content": "<p>Quiz kan støtte genkaldelse og feedback.</p>",
          "completion": {"tracking": "manual"}
        },
        {
          "id": "practise",
          "type": "quiz",
          "name": "Afprøv din viden",
          "gift": "::Q1::Hvad er formålet med en formativ quiz? {=Give feedback ~Provide a final grade}",
          "attempts": 0,
          "completion": {"tracking": "auto", "minattempts": 1}
        }
      ]
    }
  ]
}
```

## Regler

- Maksimalt 2 MiB JSON, 24 sektioner, 60 aktiviteter og 40 kapitler pr. bog.
- Undersektioner kan indeholde aktiviteter, men kan ikke indeholde nye undersektioner.
- Understøttede typer: `page`, `label`, `url`, `forum`, `assign`, `quiz`, `book`, `h5p`, `subsection`.
- H5P kræver et allerede uploadet **bruger-ejet** `.h5p`-kladde-id (`draftitemid`). Store base64-pakker sendes ikke gennem modellen.
- Grid-billeder tager `svg` eller `imageurl`, og altid `alttext`; den eksisterende billedfunktion står for sikkerhedsvalidering.
- Ukendte felter afvises frem for at blive ignoreret.
- Quizspørgsmål sendes som GIFT, kapitler som et samlet `chapters`-array.
- Sektionerne tildeles 1..N. Sektion 0 er som hidtil kursusets generelle topsektion.
- `expectedhash` kan bindes til SHA-256 fra valideringskaldet, så utilsigtede ændringer mellem validering og oprettelse afvises.

## Fejl, sikkerhed og publicering

Kurset oprettes **altid skjult**. Kun når alle sektioner og aktiviteter er oprettet uden fejl og `course.visible=true`, bliver kurset offentliggjort. Ved en fejl bliver det delvist oprettede kursus bevaret, men forsøges holdt skjult. Retursvaret indeholder `status=partial`, `courseid`, `failedpath` og oprettede antal; der sker **ingen automatisk sletning eller genforsøg**.

Kursets `shortname` er stadig unikt. Et nyt kald med samme shortname efter et tidligere forsøg bliver afvist. Det forhindrer tavs dobbeltoprettelse, men er **ikke fuld idempotent genoptagelse**. Genoptagelse, vedvarende jobstatus og planlagte Moodle-adhoc-tasks er forslag til næste udgave.

Den nuværende løsning er synkron og har derfor en grænse på 60 aktiviteter. Ved større kurser bør arbejdet på sigt placeres i Moodles opgavekø og styres med job-id, genoptagelse, fremdrift og rate limits. Før drift bør serverens timeout og ydeevne måles med realistiske kurser. Ingen antagelse om fuld transaktionssikkerhed på tværs af filer, aktiviteter og events.

## Tokenforbrug

En enkelt JSON-indsendelse kan erstatte mange separate MCP-kald. Det reducerer værktøjsbeskrivelser, mellemresultater og gentagen kontekst, men ikke de tokens, som AI bruger til selve kursusindholdet. Moodle-serveren kan ikke måle ChatGPTs reelle tokenforbrug uden input fra AI-klientens måling. `estimatedlegacycalls` er derfor kun et overslag over værktøjskald, **ikke et mål for sparede tokens**.

## Installation og test

1. Installer på **testmiljø** ved at opdatere pluginets filer i `/local/aimcp/`.
2. Kør Moodles almindelige pluginopgradering (`admin/cli/upgrade.php`).
3. Kontrollér at de to nye funktioner fremgår af `aimcp_internal` og `aimcp_external`, og opdatér MCP-forbindelsen.
4. Kør `vendor/bin/phpunit --testsuite local_aimcp_testsuite` i et Moodle 5.2-testmiljø.
5. Test en lille blueprint med en skjult kursusoprettelse og et realistisk kursus med quiz, bog, H5P og Grid-billeder.
6. Kontrollér, at fejl giver skjult delresultat og ingen ekstra kursusduplikater.
7. Udrul først derefter til drift. Eksisterende MCP-funktioner og serviceadgang bevares.