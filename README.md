# Translation checker

This small application is doing two things.

1. Open issues when there is missing translation for a language
2. Update the translation dashboard https://symfony-translations.nyholm.tech/

## Design or "architecture"

This app was quickly hacked together in October 2020 as a way to make sure the
translations were all up-to-date. The design is not perfect or beautiful but it
is semi working. The project was open sourced in May 2021.

There is a CI job that periodically looks at the lowest maintained version of Symfony
and collects data about missing translations. That data is saved in `config/data/*.json`.

If the data changes, it will update the website.

Another command is running to look at the data and create/update issues on symfony/symfony
for the missing translations.
