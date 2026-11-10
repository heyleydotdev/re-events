# Maps

ReEvents renders venue maps with OpenStreetMap by default.

## Google Maps

1. Create a browser API key with the Maps JavaScript API and Geocoding API enabled.
2. Open **ReEvents → Settings → General**.
3. Set **Map provider** to **Google Maps** and paste the key into **Google Maps API key**.

Venue addresses are geocoded when a venue is saved and has no coordinates yet. Existing coordinates are never overwritten, so you can fix a marker by hand.

Leave the key empty to keep OpenStreetMap. Clear the coordinates to geocode a venue again.
