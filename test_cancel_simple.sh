#!/bin/bash

echo "🧪 Test d'annulation de trajet avec vérification des logs"
echo "======================================================="
echo ""

# Variables
API_URL="http://localhost:8080"
DRIVER_EMAIL="jean.martin@example.com"
DRIVER_PASSWORD="Password123"

# 1. Se connecter avec le chauffeur
echo "1️⃣  Connexion avec le chauffeur..."
LOGIN=$(curl -s -X POST "$API_URL/api/auth/login" \
  -H "Content-Type: application/json" \
  -d "{\"email\": \"$DRIVER_EMAIL\", \"password\": \"$DRIVER_PASSWORD\"}" \
  -c /tmp/cookies.txt)

TOKEN=$(echo $LOGIN | jq -r '.data.token // empty' 2>/dev/null)
SUCCESS=$(echo $LOGIN | jq -r '.success // false' 2>/dev/null)

if [ "$SUCCESS" != "true" ]; then
  echo "❌ Erreur de connexion"
  echo $LOGIN | jq .
  exit 1
fi

echo "✅ Connexion réussie!"

# 2. Récupérer un trajet du chauffeur
echo ""
echo "2️⃣  Récupération des trajets du chauffeur..."
TRIPS=$(curl -s -X GET "$API_URL/api/historique/trajets" \
  -b /tmp/cookies.txt \
  -H "Cookie: token=$TOKEN")

TRIP=$(echo $TRIPS | jq '.data[] | select(.role == "chauffeur" and .statut != "annulee") | first' 2>/dev/null)

if [ -z "$TRIP" ]; then
  echo "❌ Aucun trajet de chauffeur trouvé"
  echo $TRIPS | jq .
  exit 1
fi

TRIP_ID=$(echo $TRIP | jq -r '.trajet_id // .covoiturage_id // .id' 2>/dev/null)
TRIP_LIEU=$(echo $TRIP | jq -r '.lieu_depart' 2>/dev/null)

echo "✅ Trajet trouvé:"
echo "   ID: $TRIP_ID"
echo "   Lieu: $TRIP_LIEU"
echo "   Statut: $(echo $TRIP | jq -r '.statut')"

# 3. Compter les participants confirmés
echo ""
echo "3️⃣  Vérification des participants confirmés..."

# Récupérer tous les trajets et filtrer les participations pour ce trajet
PARTICIPATIONS=$(curl -s -X GET "$API_URL/api/historique/trajets" \
  -b /tmp/cookies.txt | jq ".data[] | select((.trajet_id == \"$TRIP_ID\" or .covoiturage_id == \"$TRIP_ID\") and .role == \"passager\")" 2>/dev/null)

CONFIRMED_COUNT=$(echo "$PARTICIPATIONS" | jq 'select(.statut == "confirmee")' 2>/dev/null | grep -c "statut" || echo "0")

echo "✅ Participants: $(echo "$PARTICIPATIONS" | grep -c "role")"
echo "   Confirmés: $CONFIRMED_COUNT"

# 4. Récupérer le CSRF token
echo ""
echo "4️⃣  Récupération du token CSRF..."
CSRF=$(curl -s -X GET "$API_URL/index.php" \
  -b /tmp/cookies.txt | grep -oP "(?<=name=\"csrf_token\"\s+value=\")[^\"]*" | head -1)

if [ -z "$CSRF" ]; then
  echo "⚠️  Token CSRF non trouvé, tentative sans..."
else
  echo "✅ Token CSRF obtenu"
fi

# 5. Tester l'annulation
echo ""
echo "5️⃣  Annulation du trajet..."

CANCEL=$(curl -s -X POST "$API_URL/api/trajets/$TRIP_ID/annuler" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: $CSRF" \
  -b /tmp/cookies.txt \
  -d '{"raison": "Test annulation automatique"}' -v 2>&1)

echo $CANCEL | jq . 2>/dev/null || echo "Réponse brute:"
echo $CANCEL

# 6. Attendre et vérifier les logs
echo ""
echo "6️⃣  Vérification des logs PHP..."
sleep 3

echo ""
echo "📧 Logs récents (grep '📧|sendCancellation|EmailService|error_log'):"
docker compose logs php 2>&1 | grep -E "(📧|sendCancellation|EmailService|error_log)" | tail -15 || echo "Aucun log trouvé"

echo ""
echo "✅ Test terminé!"
