const crypto = require('crypto');

module.exports = async function handler(request, response) {
  if (request.method !== 'POST') return response.status(405).json({ error: 'Méthode non autorisée.' });

  const adminPassword = process.env.ADMIN_DELETE_PASSWORD;
  const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  const supabaseUrl = process.env.SUPABASE_URL;
  if (!adminPassword || !serviceRoleKey || !supabaseUrl) {
    return response.status(503).json({ error: 'La suppression sécurisée n’est pas configurée sur le serveur.' });
  }

  const { id, password } = request.body || {};
  if (typeof id !== 'string' || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(id) ||
      typeof password !== 'string') {
    return response.status(400).json({ error: 'Identifiant ou code invalide.' });
  }

  const expected = Buffer.from(adminPassword);
  const received = Buffer.from(password);
  if (expected.length !== received.length || !crypto.timingSafeEqual(expected, received)) {
    return response.status(401).json({ error: 'Code de suppression incorrect.' });
  }

  try {
    const url = new URL('/rest/v1/pointages', supabaseUrl);
    url.searchParams.set('id', `eq.${id}`);
    const supabaseResponse = await fetch(url, {
      method: 'DELETE',
      headers: {
        apikey: serviceRoleKey,
        Authorization: `Bearer ${serviceRoleKey}`,
        Prefer: 'return=representation'
      }
    });

    if (!supabaseResponse.ok) {
      console.error('Erreur Supabase lors de la suppression:', supabaseResponse.status);
      return response.status(502).json({ error: 'Supabase a refusé la suppression.' });
    }

    const deleted = await supabaseResponse.json();
    if (!deleted.length) return response.status(404).json({ error: 'Ce pointage est introuvable.' });
    return response.status(200).json({ success: true });
  } catch (error) {
    console.error('Erreur de suppression du pointage:', error);
    return response.status(500).json({ error: 'Erreur interne lors de la suppression.' });
  }
};