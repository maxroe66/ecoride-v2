#!/bin/bash

# Script de test pour l'annulation de trajet et vérification des emails en logs

echo "🧪 Démarrage du test d'annulation..."
echo "=================================="

# 1. Récupérer un trajet avec ses participants
echo ""
echo "1️⃣  Récupération d'un trajet avec participants..."
TRIP=$(curl -s -H "Cookie: PHPSESSID=test_session" \
  "http://localhost/api/historique/trajets" | \
  jq '.data[] | select(.role == "chauffeur") | first' 2>/dev/null)

if [ -z "$TRIP" ]; then
  echo "❌ Aucun trajet trouvé. Création d'un trajet de test..."
  
  # Créer un utilisateur test chauffeur
  SIGNUP=$(curl -s -X POST "http://localhost/api/auth/signup" \
    -H "Content-Type: application/json" \
    -d '{
      "email": "chauffeur@test.com",
      "password": "test123",
      "nom": "Test",
      "prenom": "Chauffeur",
      "pseudo": "chauffeur_test"
    }')
  
  echo "✅ Utilisateur créé: $(echo $SIGNUP | jq -r '.data.id' 2>/dev/null)"
  
  exit 1
fi

TRIP_ID=$(echo "$TRIP" | jq -r '.trajet_id // .covoiturage_id // .id' 2>/dev/null)
TRIP_PRICE=$(echo "$TRIP" | jq -r '.prix // .prix_total // 0' 2>/dev/null)

echo "✅ Trajet trouvé: ID=$TRIP_ID, Prix=$TRIP_PRICE"

# 2. Récupérer les participants confirmés
echo ""
echo "2️⃣  Récupération des participants..."
PARTICIPANTS=$(curl -s -H "Cookie: PHPSESSID=test_session" \
  "http://localhost/api/historique/trajets" | \
  jq ".data[] | select(.trajet_id == \"$TRIP_ID\" or .covoiturage_id == \"$TRIP_ID\") | select(.role == \"passager\") | .statut" 2>/dev/null)

CONFIRMED_COUNT=$(echo "$PARTICIPANTS" | grep -c "confirmee" 2>/dev/null || echo "0")
echo "✅ Participants confirmés: $CONFIRMED_COUNT"

# 3. Tester l'annulation du trajet
echo ""
echo "3️⃣  Annulation du trajet (chauffeur)..."

CANCEL=$(curl -s -X POST "http://localhost/api/trajets/$TRIP_ID/annuler" \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=test_session" \
  -d '{"raison": "Test annulation"}')

NOTIFIED=$(echo "$CANCEL" | jq -r '.participants_notified // "?" ' 2>/dev/null)
SUCCESS=$(echo "$CANCEL" | jq -r '.success // false' 2>/dev/null)

echo "📧 Réponse: $CANCEL"
echo ""

if [ "$SUCCESS" = "true" ]; then
  echo "✅ Annulation réussie!"
  echo "📧 Participants notifiés: $NOTIFIED"
else
  echo "❌ Annulation échouée"
fi

# 4. Vérifier les logs pour voir les emails
echo ""
echo "4️⃣  Vérification des logs PHP pour les emails..."
echo "================================================"

# Attendre un peu que les logs s'écrivent
sleep 2

# Récupérer les logs depuis les 30 dernières secondes
docker compose logs php 2>&1 | grep -E "(📧|sendCancellation|EmailService)" | tail -20

echo ""
echo "✅ Test terminé!"
