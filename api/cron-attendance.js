const PAGE_SIZE = 500;
const RECIPIENTS = [
  'informaticien@mediayab.com',
  'rh@mediayab.com',
  'b.nguessan@mediayab.com'
];

async function fetchSupabaseRows(table, columns, filters = {}) {
  const supabaseUrl = process.env.SUPABASE_URL;
  const supabaseKey = process.env.SUPABASE_ANON_KEY;
  if (!supabaseUrl || !supabaseKey) {
    throw new Error('SUPABASE_URL et SUPABASE_ANON_KEY doivent être configurés sur Vercel.');
  }

  const rows = [];
  for (let offset = 0; ; offset += PAGE_SIZE) {
    const url = new URL(`/rest/v1/${table}`, supabaseUrl);
    url.searchParams.set('select', columns);
    for (const [name, value] of Object.entries(filters)) url.searchParams.set(name, value);
    if (table === 'pointages') url.searchParams.set('order', 'created_at.desc,id.desc');

    const response = await fetch(url, {
      headers: {
        apikey: supabaseKey,
        Authorization: `Bearer ${supabaseKey}`,
        'Range-Unit': 'items',
        Range: `${offset}-${offset + PAGE_SIZE - 1}`
      }
    });
    if (!response.ok) throw new Error(`Lecture Supabase ${table} refusée (${response.status}).`);

    const page = await response.json();
    if (!Array.isArray(page)) throw new Error(`Réponse Supabase invalide pour ${table}.`);
    rows.push(...page);
    if (page.length < PAGE_SIZE) return rows;
  }
}

async function sendMail(payload) {
  const mailEndpoint = process.env.MAIL_ENDPOINT_URL;
  if (!mailEndpoint) throw new Error('MAIL_ENDPOINT_URL doit pointer vers le send_mail.php hébergé.');

  const response = await fetch(mailEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });
  const result = await response.json().catch(() => ({}));
  if (!response.ok || !result.success) {
    throw new Error(result.message || `Échec du mailer PHP (${response.status}).`);
  }
}

function getDailyPointage(pointages, agentName) {
  return pointages.find(pointage => pointage.name === agentName &&
    pointage.arrivee && pointage.arrivee !== 'NON POINTE') ||
    pointages.find(pointage => pointage.name === agentName);
}

module.exports = async function handler(request, response) {
  if (request.method !== 'GET') return response.status(405).json({ error: 'Méthode non autorisée.' });

  const cronSecret = process.env.CRON_SECRET;
  if (!cronSecret || request.headers.authorization !== `Bearer ${cronSecret}`) {
    return response.status(401).json({ error: 'Non autorisé.' });
  }

  const requestUrl = new URL(request.url, `https://${request.headers.host || 'localhost'}`);
  const reportType = request.query?.type || requestUrl.searchParams.get('type');
  if (!['check', 'summary'].includes(reportType)) {
    return response.status(400).json({ error: 'Type de rapport invalide.' });
  }

  try {
    const today = new Date().toISOString().slice(0, 10);
    const [allAgents, pointages] = await Promise.all([
      fetchSupabaseRows('agents', 'name,is_active,date_sortie'),
      fetchSupabaseRows('pointages', 'name,iso_date,arrivee,depart,total,status', {
        iso_date: `eq.${today}`
      })
    ]);
    const agents = allAgents.filter(agent => agent.is_active !== false && !agent.date_sortie);

    let payload;
    if (reportType === 'check') {
      const items = agents.flatMap(agent => {
        const pointage = getDailyPointage(pointages, agent.name);
        if (!pointage || !pointage.arrivee || pointage.arrivee === 'NON POINTE' ||
            (pointage.status || '').includes('ABSENCE')) {
          return [{ name: agent.name, arrivee: pointage?.arrivee || '-', status: 'ABSENT / NON POINTE' }];
        }
        if (pointage.status === 'RETARD') {
          return [{ name: agent.name, arrivee: pointage.arrivee, status: 'EN RETARD' }];
        }
        return [];
      });

      payload = { isAttendanceCheck: true, date: today, items, recipients: RECIPIENTS };
    } else {
      const items = agents.map(agent => {
        const pointage = getDailyPointage(pointages, agent.name);
        return {
          name: agent.name,
          arrivee: pointage?.arrivee || 'NON POINTE',
          depart: pointage?.depart || '-',
          total: pointage?.total || '0',
          status: pointage?.status || 'ABSENCE CONSTATED'
        };
      });

      payload = { isSummary: true, date: today, items, recipients: RECIPIENTS };
    }

    await sendMail(payload);
    return response.status(200).json({ success: true, type: reportType, date: today, count: payload.items.length });
  } catch (error) {
    console.error('Échec du rapport planifié de pointage:', error);
    return response.status(500).json({ error: 'Le rapport planifié n’a pas pu être envoyé.' });
  }
};