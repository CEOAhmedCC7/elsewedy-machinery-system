--
-- PostgreSQL database dump
--

\restrict IKXhqxruZPCgOIds3GcvBB0kofEa9lRTI9BhuuLUFX7yqSRtIe3a1kxpkxVjW0u

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2025-11-30 11:21:45

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 221 (class 1259 OID 24742)
-- Name: batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.batches (
    batch_id character varying(50) NOT NULL,
    project_id character varying(50),
    batch_name character varying(255) NOT NULL
);


ALTER TABLE public.batches OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 24768)
-- Name: budgets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.budgets (
    budget_id character varying(50) NOT NULL,
    project_id character varying(50),
    sub_batch_detail_id character varying(50),
    cost_type character varying(50),
    revenue_amount numeric(18,2),
    revenue_currency character varying(3),
    revenue_exchange_rate numeric(10,4),
    freight_amount numeric(18,2),
    freight_currency character varying(3),
    freight_exchange_rate numeric(10,4),
    supplier_cost_amount numeric(18,2),
    supplier_cost_currency character varying(3),
    supplier_cost_exchange_rate numeric(10,4),
    CONSTRAINT budgets_check CHECK ((((project_id IS NOT NULL) AND (sub_batch_detail_id IS NULL)) OR ((project_id IS NULL) AND (sub_batch_detail_id IS NOT NULL))))
);


ALTER TABLE public.budgets OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 24718)
-- Name: customers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.customers (
    customer_id character varying(50) NOT NULL,
    customer_name character varying(255) NOT NULL
);


ALTER TABLE public.customers OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 24804)
-- Name: invoices; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.invoices (
    invoice_id character varying(50) NOT NULL,
    project_id character varying(50),
    description text,
    invoice_number character varying(100) NOT NULL,
    invoice_date date,
    total_amount numeric(18,2),
    vat_amount numeric(18,2),
    amount_with_vat numeric(18,2),
    status character varying(50),
    collected_date date
);


ALTER TABLE public.invoices OWNER TO postgres;

--
-- TOC entry 224 (class 1259 OID 24785)
-- Name: payments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.payments (
    payment_id character varying(50) NOT NULL,
    project_id character varying(50),
    sub_batch_detail_id character varying(50),
    payment_code character varying(100),
    payment_type character varying(100),
    requested_by character varying(255),
    requested_date date,
    due_date date,
    paid_date date,
    status character varying(50),
    description text,
    amount numeric(18,2),
    currency character varying(3),
    exchange_rate numeric(10,4),
    CONSTRAINT payments_check CHECK ((((project_id IS NOT NULL) AND (sub_batch_detail_id IS NULL)) OR ((project_id IS NULL) AND (sub_batch_detail_id IS NOT NULL))))
);


ALTER TABLE public.payments OWNER TO postgres;

--
-- TOC entry 220 (class 1259 OID 24725)
-- Name: projects; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.projects (
    project_id character varying(50) NOT NULL,
    project_name character varying(255) NOT NULL,
    cost_center_no character varying(100) NOT NULL,
    po_number character varying(100),
    customer_id character varying(50),
    contract_date date,
    expected_end_date date,
    actual_end_date date
);


ALTER TABLE public.projects OWNER TO postgres;

--
-- TOC entry 222 (class 1259 OID 24754)
-- Name: sub_batch_details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sub_batch_details (
    sub_batch_detail_id character varying(50) NOT NULL,
    batch_id character varying(50),
    sub_batch_name character varying(255) NOT NULL,
    description text
);


ALTER TABLE public.sub_batch_details OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 24818)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    user_id character varying(50) NOT NULL,
    username character varying(100) NOT NULL,
    password_hash text NOT NULL,
    role character varying(50) NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_role_check CHECK (((role)::text = ANY ((ARRAY['admin'::character varying, 'project_manager'::character varying, 'finance'::character varying, 'viewer'::character varying])::text[]))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'inactive'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 5019 (class 0 OID 24742)
-- Dependencies: 221
-- Data for Name: batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.batches (batch_id, project_id, batch_name) FROM stdin;
\.


--
-- TOC entry 5021 (class 0 OID 24768)
-- Dependencies: 223
-- Data for Name: budgets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.budgets (budget_id, project_id, sub_batch_detail_id, cost_type, revenue_amount, revenue_currency, revenue_exchange_rate, freight_amount, freight_currency, freight_exchange_rate, supplier_cost_amount, supplier_cost_currency, supplier_cost_exchange_rate) FROM stdin;
\.


--
-- TOC entry 5017 (class 0 OID 24718)
-- Dependencies: 219
-- Data for Name: customers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.customers (customer_id, customer_name) FROM stdin;
\.


--
-- TOC entry 5023 (class 0 OID 24804)
-- Dependencies: 225
-- Data for Name: invoices; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoices (invoice_id, project_id, description, invoice_number, invoice_date, total_amount, vat_amount, amount_with_vat, status, collected_date) FROM stdin;
\.


--
-- TOC entry 5022 (class 0 OID 24785)
-- Dependencies: 224
-- Data for Name: payments; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.payments (payment_id, project_id, sub_batch_detail_id, payment_code, payment_type, requested_by, requested_date, due_date, paid_date, status, description, amount, currency, exchange_rate) FROM stdin;
\.


--
-- TOC entry 5018 (class 0 OID 24725)
-- Dependencies: 220
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.projects (project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date) FROM stdin;
\.


--
-- TOC entry 5020 (class 0 OID 24754)
-- Dependencies: 222
-- Data for Name: sub_batch_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sub_batch_details (sub_batch_detail_id, batch_id, sub_batch_name, description) FROM stdin;
\.


--
-- TOC entry 5024 (class 0 OID 24818)
-- Dependencies: 226
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (user_id, username, password_hash, role, status, created_at) FROM stdin;
U001	Ahmed Amr	Admin123	admin	active	2025-11-30 10:56:39.684802
\.


--
-- TOC entry 4849 (class 2606 OID 24748)
-- Name: batches batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.batches
    ADD CONSTRAINT batches_pkey PRIMARY KEY (batch_id);


--
-- TOC entry 4853 (class 2606 OID 24774)
-- Name: budgets budgets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_pkey PRIMARY KEY (budget_id);


--
-- TOC entry 4843 (class 2606 OID 24724)
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (customer_id);


--
-- TOC entry 4857 (class 2606 OID 24812)
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (invoice_id);


--
-- TOC entry 4855 (class 2606 OID 24793)
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (payment_id);


--
-- TOC entry 4845 (class 2606 OID 24736)
-- Name: projects projects_cost_center_no_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_cost_center_no_key UNIQUE (cost_center_no);


--
-- TOC entry 4847 (class 2606 OID 24734)
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (project_id);


--
-- TOC entry 4851 (class 2606 OID 24762)
-- Name: sub_batch_details sub_batch_details_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sub_batch_details
    ADD CONSTRAINT sub_batch_details_pkey PRIMARY KEY (sub_batch_detail_id);


--
-- TOC entry 4859 (class 2606 OID 24832)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 4861 (class 2606 OID 24834)
-- Name: users users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_username_key UNIQUE (username);


--
-- TOC entry 4863 (class 2606 OID 24749)
-- Name: batches batches_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.batches
    ADD CONSTRAINT batches_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 4865 (class 2606 OID 24775)
-- Name: budgets budgets_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 4866 (class 2606 OID 24780)
-- Name: budgets budgets_sub_batch_detail_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_sub_batch_detail_id_fkey FOREIGN KEY (sub_batch_detail_id) REFERENCES public.sub_batch_details(sub_batch_detail_id);


--
-- TOC entry 4869 (class 2606 OID 24813)
-- Name: invoices invoices_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 4867 (class 2606 OID 24794)
-- Name: payments payments_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 4868 (class 2606 OID 24799)
-- Name: payments payments_sub_batch_detail_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_sub_batch_detail_id_fkey FOREIGN KEY (sub_batch_detail_id) REFERENCES public.sub_batch_details(sub_batch_detail_id);


--
-- TOC entry 4862 (class 2606 OID 24737)
-- Name: projects projects_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(customer_id);


--
-- TOC entry 4864 (class 2606 OID 24763)
-- Name: sub_batch_details sub_batch_details_batch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sub_batch_details
    ADD CONSTRAINT sub_batch_details_batch_id_fkey FOREIGN KEY (batch_id) REFERENCES public.batches(batch_id);


-- Completed on 2025-11-30 11:21:46

--
-- PostgreSQL database dump complete
--

\unrestrict IKXhqxruZPCgOIds3GcvBB0kofEa9lRTI9BhuuLUFX7yqSRtIe3a1kxpkxVjW0u

