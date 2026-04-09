# Faza 3 — Bootstrap module Baptism + Birthday

## Ce exista înainte
- resolver/storage per verticală funcționale
- module `baptism`/`birthday` prezente doar ca placeholders minime

## Ce completează Faza 3
1. Module bootstrap real în plugin
- ambele module sunt încărcate în bootstrap-ul pluginului
- fiecare modul își înregistrează runtime contract-ul propriu

2. Boundary contracts complete (scaffold)
Pentru ambele module există boundary-uri explicite pentru:
- payload
- renderer
- preview/PDF
- admin-client
- invitati
- RSVP
- reports
- gifts integration (engine shared)
- email semantics (engine shared)

3. Placeholder service providers expliciți
- fiecare boundary are provider placeholder not-ready
- runtime public rămâne dezactivat (`public_runtime_enabled=false`)

4. Runtime registry pentru module
- infrastructura shared poate expune runtime contract per verticală și per token

## Ce NU include Faza 3
- fără payload final
- fără renderer final
- fără preview/PDF final
- fără binding final ACF pentru admin-client/invitati
- fără expunere flow-uri publice incomplete pentru noile verticale
