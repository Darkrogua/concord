--
-- PostgreSQL database dump
--

\restrict 3FhgpVdnhBGSZUkMWEJSsuOIdcfLzeLywL4RSjFgCt71N6GDl8CaXGjDnCoGnLw

-- Dumped from database version 16.10
-- Dumped by pg_dump version 16.10

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: backend_access_log; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_access_log (id, user_id, ip_address, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: backend_user_groups; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_user_groups (id, name, code, description, is_new_user_default, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: backend_user_preferences; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_user_preferences (id, user_id, namespace, "group", item, value, site_id, site_root_id) FROM stdin;
1	1	backend	hints	hidden	{"builder-version-rollback":1,"builder-version-apply":1}	\N	\N
2	1	backend	backend	preferences	{"locale":"ru","fallback_locale":"ru","timezone":"UTC","editor_theme":"twilight","editor_word_wrap":"off","editor_font_size":"16","editor_tab_size":"4","editor_code_folding":"manual","editor_autocompletion":"manual","editor_use_emmet":"1","editor_show_gutter":"1","editor_highlight_active_line":"1","editor_auto_closing":"1","editor_use_hard_tabs":"0","editor_display_indent_guides":"0","editor_show_invisibles":"1","editor_show_print_margin":"0"}	\N	\N
\.


--
-- Data for Name: backend_user_roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_user_roles (id, name, code, color_background, description, permissions, is_system, sort_order, created_at, updated_at) FROM stdin;
\.


--
-- Data for Name: backend_user_throttle; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_user_throttle (id, user_id, ip_address, attempts, last_attempt_at, is_suspended, suspended_at, is_banned, banned_at) FROM stdin;
1	1	172.18.0.2	0	\N	f	\N	f	\N
2	1	172.18.0.3	0	\N	f	\N	f	\N
\.


--
-- Data for Name: backend_users; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_users (id, first_name, last_name, login, email, password, activation_code, persist_code, reset_password_code, permissions, is_activated, is_superuser, activated_at, last_login, deleted_at, role_id, created_at, updated_at, is_password_expired, password_changed_at, reset_password_at) FROM stdin;
1	Al	Blaze	zen	zen@8ber.ru	$2y$10$FPEaH0XpP0DNKi2ZuqrDlOwuHG9tCokLPCqpo3LHnIPdU8eYeaDIC	\N	$2y$10$YQGzXuDJLWRPxVucbgFcP.ixIT22YMF/sEo.g05NwjMyZhs5Ln4bK	\N		t	t	\N	2026-04-28 19:22:04	\N	\N	2026-04-28 19:22:04	2026-04-28 19:22:04	f	2026-04-28 19:22:04	\N
\.


--
-- Data for Name: backend_users_groups; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.backend_users_groups (user_id, user_group_id) FROM stdin;
\.


--
-- Name: backend_access_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_access_log_id_seq', 1, false);


--
-- Name: backend_user_groups_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_user_groups_id_seq', 1, false);


--
-- Name: backend_user_preferences_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_user_preferences_id_seq', 2, true);


--
-- Name: backend_user_roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_user_roles_id_seq', 1, false);


--
-- Name: backend_user_throttle_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_user_throttle_id_seq', 2, true);


--
-- Name: backend_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.backend_users_id_seq', 1, true);


--
-- PostgreSQL database dump complete
--

\unrestrict 3FhgpVdnhBGSZUkMWEJSsuOIdcfLzeLywL4RSjFgCt71N6GDl8CaXGjDnCoGnLw

