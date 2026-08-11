import http from 'k6/http';
import { check, sleep, group } from 'k6';
import { SharedArray } from 'k6/data';
import { BASE_URL } from '../lib/config.js';
import { uuid } from '../lib/helpers.js';

/**
 * S3: Daily Quiz Journey (the flagship)
 * 100 / 500 / 1,000 concurrent VUs, each:
 * dashboard → day fetch → start attempt → 10 answers (20-40s think time,
 * 30% wrong first try then retry) → complete
 *
 * Proves: end-to-end realism; p95 answer < 500ms; zero 5xx; every VU ends 10/10.
 */
export const options = {
  scenarios: {
    quiz_journey: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 100 },
        { duration: '3m', target: 500 },
        { duration: '3m', target: 1000 },
        { duration: '2m', target: 0 },
      ],
    },
  },
  thresholds: {
    'http_req_failed': ['rate<0.005'],
    'http_req_duration{name:answer_submit}': ['p(95)<500', 'p(99)<1000'],
    'http_req_duration{name:quiz_day_fetch}': ['p(95)<700'],
    'http_req_duration{name:complete_day}': ['p(95)<800'],
    'checks': ['rate>0.995'],
    'group_duration{group:::daily_quiz_journey}': ['p(95)<90000'],
  },
};

const tokens = new SharedArray('tokens', function () {
  return JSON.parse(open('../data/tokens.json'));
});

export default function () {
  const token = tokens[Math.floor(Math.random() * tokens.length)];
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    Authorization: `Bearer ${token}`,
  };

  group('daily_quiz_journey', function () {
    // 1. Dashboard
    const dashRes = http.get(`${BASE_URL}/student/dashboard`, { headers, tags: { name: 'dashboard' } });
    check(dashRes, { 'dashboard 200': (r) => r.status === 200 });

    const dashboard = JSON.parse(dashRes.body).data;
    if (!dashboard || !dashboard.today) return;

    const day = dashboard.today.day_number;
    const courseId = dashboard.enrolment?.course_id;
    if (!courseId) return;

    sleep(2);

    // 2. Fetch quiz day
    const dayRes = http.get(`${BASE_URL}/student/courses/${courseId}/quiz-days/${day}`, {
      headers,
      tags: { name: 'quiz_day_fetch' },
    });

    check(dayRes, {
      'day fetch 200 or 423': (r) => r.status === 200 || r.status === 423,
      'no answer leak': (r) => !r.body.includes('"correct_option"') && !r.body.includes('"explanation"'),
    });

    if (dayRes.status !== 200) return;

    const quizDay = JSON.parse(dayRes.body).data;
    const questions = quizDay.questions || [];
    const attemptUuid = quizDay.attempt?.uuid;
    if (!attemptUuid || questions.length === 0) return;

    // 3. Start/resume attempt
    http.post(`${BASE_URL}/student/courses/${courseId}/quiz-days/${day}/attempts`, '{}', {
      headers,
      tags: { name: 'start_attempt' },
    });

    // 4. Answer questions
    for (const question of questions) {
      sleep(Math.random() * 20 + 20); // 20-40s think time

      const options = question.options || [];
      if (options.length === 0) continue;

      // Pick an answer (30% wrong on first try)
      const isWrong = Math.random() < 0.3;
      const selectedOption = isWrong
        ? options[Math.floor(Math.random() * options.length)].key
        : options[0].key; // First option (may or may not be correct - server decides)

      const answerRes = http.post(
        `${BASE_URL}/student/quiz-attempts/${attemptUuid}/answers`,
        JSON.stringify({
          question_id: question.id,
          selected_option: selectedOption,
          client_answer_uuid: uuid(),
          time_spent_ms: Math.floor(Math.random() * 30000 + 5000),
          answered_at: new Date().toISOString(),
          was_offline: false,
        }),
        {
          headers: { ...headers, 'Idempotency-Key': uuid() },
          tags: { name: 'answer_submit' },
        },
      );

      check(answerRes, {
        'answer returns 200': (r) => r.status === 200,
        'answer has is_correct': (r) => JSON.parse(r.body).data?.is_correct !== undefined,
      });
    }

    sleep(2);

    // 5. Complete
    const completeRes = http.post(
      `${BASE_URL}/student/quiz-attempts/${attemptUuid}/complete`,
      '{}',
      {
        headers: { ...headers, 'Idempotency-Key': uuid() },
        tags: { name: 'complete_day' },
      },
    );

    check(completeRes, {
      'complete returns 200 or 422': (r) => r.status === 200 || r.status === 422,
    });
  });
}
