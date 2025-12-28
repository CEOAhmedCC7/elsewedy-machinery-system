--
-- PostgreSQL database dump
--

\restrict n2fd4H9czg2LPryKlc7YxGM15ZxUnWHLPejOIU4vsb6Pk7qy2ekm2qB6ZKhbkRA

-- Dumped from database version 18.1
-- Dumped by pg_dump version 18.1

-- Started on 2025-12-28 10:32:29

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
-- TOC entry 243 (class 1259 OID 42045)
-- Name: batches; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.batches (
    batch_id integer NOT NULL,
    project_id integer NOT NULL,
    batch_name character varying(255) NOT NULL
);


ALTER TABLE public.batches OWNER TO postgres;

--
-- TOC entry 242 (class 1259 OID 42044)
-- Name: batches_batch_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.batches_batch_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.batches_batch_id_seq OWNER TO postgres;

--
-- TOC entry 5351 (class 0 OID 0)
-- Dependencies: 242
-- Name: batches_batch_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.batches_batch_id_seq OWNED BY public.batches.batch_id;


--
-- TOC entry 261 (class 1259 OID 42255)
-- Name: budget_update_requests; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.budget_update_requests (
    request_id integer NOT NULL,
    budget_id integer,
    project_id integer,
    sub_batch_detail_id integer,
    supplier_id integer,
    request_code character varying(100),
    request_type character varying(50),
    status character varying(50),
    reason text,
    requested_by_user_id integer NOT NULL,
    requested_date date,
    approved_by_user_id integer,
    approved_date date,
    implemented_date date,
    CONSTRAINT budget_update_requests_check CHECK (((budget_id IS NOT NULL) OR (project_id IS NOT NULL) OR (sub_batch_detail_id IS NOT NULL)))
);


ALTER TABLE public.budget_update_requests OWNER TO postgres;

--
-- TOC entry 260 (class 1259 OID 42254)
-- Name: budget_update_requests_request_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.budget_update_requests_request_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.budget_update_requests_request_id_seq OWNER TO postgres;

--
-- TOC entry 5352 (class 0 OID 0)
-- Dependencies: 260
-- Name: budget_update_requests_request_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.budget_update_requests_request_id_seq OWNED BY public.budget_update_requests.request_id;


--
-- TOC entry 259 (class 1259 OID 42229)
-- Name: budgets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.budgets (
    budget_id integer NOT NULL,
    project_id integer,
    sub_batch_detail_id integer,
    cost_type character varying(100),
    revenue_amount numeric,
    revenue_currency character varying(10),
    revenue_exchange_rate numeric,
    freight_amount numeric,
    freight_currency character varying(10),
    freight_exchange_rate numeric,
    supplier_cost_amount numeric,
    supplier_cost_currency character varying(10),
    supplier_cost_exchange_rate numeric,
    supplier_id integer,
    CONSTRAINT budgets_check CHECK ((((project_id IS NOT NULL) AND (sub_batch_detail_id IS NULL)) OR ((project_id IS NULL) AND (sub_batch_detail_id IS NOT NULL))))
);


ALTER TABLE public.budgets OWNER TO postgres;

--
-- TOC entry 258 (class 1259 OID 42228)
-- Name: budgets_budget_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.budgets_budget_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.budgets_budget_id_seq OWNER TO postgres;

--
-- TOC entry 5353 (class 0 OID 0)
-- Dependencies: 258
-- Name: budgets_budget_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.budgets_budget_id_seq OWNED BY public.budgets.budget_id;


--
-- TOC entry 220 (class 1259 OID 41840)
-- Name: business_lines; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.business_lines (
    business_line_id integer NOT NULL,
    business_line_name character varying(255) NOT NULL,
    bl_code character varying(50) NOT NULL,
    is_active boolean DEFAULT true
);


ALTER TABLE public.business_lines OWNER TO postgres;

--
-- TOC entry 219 (class 1259 OID 41839)
-- Name: business_lines_business_line_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.business_lines_business_line_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.business_lines_business_line_id_seq OWNER TO postgres;

--
-- TOC entry 5354 (class 0 OID 0)
-- Dependencies: 219
-- Name: business_lines_business_line_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.business_lines_business_line_id_seq OWNED BY public.business_lines.business_line_id;


--
-- TOC entry 257 (class 1259 OID 42202)
-- Name: cash_in; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_in (
    cash_in_id integer NOT NULL,
    business_line_id integer NOT NULL,
    project_id integer NOT NULL,
    description text,
    invoice_id integer,
    status character varying(20) NOT NULL,
    transaction_category character varying(255),
    amount_egp numeric,
    amount_usd numeric,
    rate numeric,
    total_value numeric,
    planned_date date,
    actual_date date,
    CONSTRAINT ck_cash_in_status CHECK (((status)::text = ANY ((ARRAY['Planned'::character varying, 'Invoiced'::character varying, 'Collected'::character varying])::text[])))
);


ALTER TABLE public.cash_in OWNER TO postgres;

--
-- TOC entry 268 (class 1259 OID 49233)
-- Name: cash_in_cash_in_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_in_cash_in_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_in_cash_in_id_seq OWNER TO postgres;

--
-- TOC entry 5355 (class 0 OID 0)
-- Dependencies: 268
-- Name: cash_in_cash_in_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_in_cash_in_id_seq OWNED BY public.cash_in.cash_in_id;


--
-- TOC entry 256 (class 1259 OID 42175)
-- Name: cash_out; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cash_out (
    cash_out_id integer NOT NULL,
    business_line_id integer NOT NULL,
    project_id integer NOT NULL,
    description text,
    supplier_invoice character varying(255),
    po_number character varying(255),
    transaction_category character varying(255),
    amount_egp numeric,
    amount_usd numeric,
    usd_exchange_rate numeric,
    amount_eur numeric,
    eur_exchange_rate numeric,
    total_value numeric,
    planned_date date,
    requested_date date,
    paid_date date,
    status character varying(20) NOT NULL,
    CONSTRAINT ck_cash_out_status CHECK (((status)::text = ANY ((ARRAY['Planned'::character varying, 'Requested'::character varying, 'Paid'::character varying])::text[])))
);


ALTER TABLE public.cash_out OWNER TO postgres;

--
-- TOC entry 269 (class 1259 OID 49263)
-- Name: cash_out_cash_out_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cash_out_cash_out_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cash_out_cash_out_id_seq OWNER TO postgres;

--
-- TOC entry 5356 (class 0 OID 0)
-- Dependencies: 269
-- Name: cash_out_cash_out_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cash_out_cash_out_id_seq OWNED BY public.cash_out.cash_out_id;


--
-- TOC entry 251 (class 1259 OID 42118)
-- Name: collection_targets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.collection_targets (
    collection_target_id integer NOT NULL,
    business_line_id integer NOT NULL,
    target_year integer NOT NULL,
    target_month integer,
    target_amount numeric,
    currency character varying(10),
    exchange_rate numeric,
    CONSTRAINT collection_targets_target_month_check CHECK (((target_month IS NULL) OR ((target_month >= 1) AND (target_month <= 12))))
);


ALTER TABLE public.collection_targets OWNER TO postgres;

--
-- TOC entry 250 (class 1259 OID 42117)
-- Name: collection_targets_collection_target_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.collection_targets_collection_target_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.collection_targets_collection_target_id_seq OWNER TO postgres;

--
-- TOC entry 5357 (class 0 OID 0)
-- Dependencies: 250
-- Name: collection_targets_collection_target_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.collection_targets_collection_target_id_seq OWNED BY public.collection_targets.collection_target_id;


--
-- TOC entry 222 (class 1259 OID 41853)
-- Name: customers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.customers (
    customer_id integer NOT NULL,
    customer_name character varying(255) NOT NULL
);


ALTER TABLE public.customers OWNER TO postgres;

--
-- TOC entry 221 (class 1259 OID 41852)
-- Name: customers_customer_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.customers_customer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.customers_customer_id_seq OWNER TO postgres;

--
-- TOC entry 5358 (class 0 OID 0)
-- Dependencies: 221
-- Name: customers_customer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.customers_customer_id_seq OWNED BY public.customers.customer_id;


--
-- TOC entry 267 (class 1259 OID 49216)
-- Name: hot_deals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.hot_deals (
    hot_deal_id integer NOT NULL,
    business_line_id integer NOT NULL,
    customer_name character varying(255) NOT NULL,
    qty numeric,
    description text,
    value numeric,
    exchange_rate numeric,
    note text,
    deal_date date NOT NULL
);


ALTER TABLE public.hot_deals OWNER TO postgres;

--
-- TOC entry 266 (class 1259 OID 49215)
-- Name: hot_deals_hot_deal_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.hot_deals_hot_deal_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.hot_deals_hot_deal_id_seq OWNER TO postgres;

--
-- TOC entry 5359 (class 0 OID 0)
-- Dependencies: 266
-- Name: hot_deals_hot_deal_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.hot_deals_hot_deal_id_seq OWNED BY public.hot_deals.hot_deal_id;


--
-- TOC entry 255 (class 1259 OID 42159)
-- Name: invoices; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.invoices (
    invoice_id integer NOT NULL,
    project_id integer NOT NULL,
    description text,
    invoice_number character varying(100),
    invoice_date date,
    total_amount numeric,
    vat_amount numeric,
    amount_with_vat numeric,
    status character varying(50),
    collected_date date,
    invoice_due_date date,
    category character varying(255),
    business_line_id integer,
    customer_id integer,
    vat_number character varying(100),
    currency character varying(3),
    collection_rate numeric,
    CONSTRAINT ck_invoices_currency CHECK ((((currency)::text = ANY ((ARRAY['EGP'::character varying, 'USD'::character varying, 'EUR'::character varying])::text[])) OR (currency IS NULL)))
);


ALTER TABLE public.invoices OWNER TO postgres;

--
-- TOC entry 254 (class 1259 OID 42158)
-- Name: invoices_invoice_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.invoices_invoice_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.invoices_invoice_id_seq OWNER TO postgres;

--
-- TOC entry 5360 (class 0 OID 0)
-- Dependencies: 254
-- Name: invoices_invoice_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.invoices_invoice_id_seq OWNED BY public.invoices.invoice_id;


--
-- TOC entry 235 (class 1259 OID 41960)
-- Name: leads_tracking; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.leads_tracking (
    id integer NOT NULL,
    platform text,
    contact_email text,
    mobile_number text,
    inquiries text,
    business_unit text,
    owner text,
    status text,
    lead_date date,
    response_date date,
    response_time numeric,
    note text
);


ALTER TABLE public.leads_tracking OWNER TO postgres;

--
-- TOC entry 234 (class 1259 OID 41959)
-- Name: leads_tracking_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.leads_tracking_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.leads_tracking_id_seq OWNER TO postgres;

--
-- TOC entry 5361 (class 0 OID 0)
-- Dependencies: 234
-- Name: leads_tracking_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.leads_tracking_id_seq OWNED BY public.leads_tracking.id;


--
-- TOC entry 239 (class 1259 OID 41997)
-- Name: lost_deals; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.lost_deals (
    lost_deal_id integer NOT NULL,
    lead_id integer,
    business_line_id integer NOT NULL,
    customer_id integer NOT NULL,
    opportunity_name character varying(255),
    stage character varying(100),
    total_value numeric,
    currency character varying(10),
    exchange_rate numeric,
    created_date date,
    closed_date date,
    loss_reason text,
    account_name character varying(255),
    opportunity_owner_id integer,
    reason text,
    note text,
    sector character varying(255)
);


ALTER TABLE public.lost_deals OWNER TO postgres;

--
-- TOC entry 238 (class 1259 OID 41996)
-- Name: lost_deals_lost_deal_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.lost_deals_lost_deal_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.lost_deals_lost_deal_id_seq OWNER TO postgres;

--
-- TOC entry 5362 (class 0 OID 0)
-- Dependencies: 238
-- Name: lost_deals_lost_deal_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.lost_deals_lost_deal_id_seq OWNED BY public.lost_deals.lost_deal_id;


--
-- TOC entry 230 (class 1259 OID 41905)
-- Name: modules; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.modules (
    module_id integer NOT NULL,
    module_code character varying(100) NOT NULL,
    module_name character varying(255) NOT NULL,
    img character varying(255)[]
);


ALTER TABLE public.modules OWNER TO postgres;

--
-- TOC entry 229 (class 1259 OID 41904)
-- Name: modules_module_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.modules_module_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.modules_module_id_seq OWNER TO postgres;

--
-- TOC entry 5363 (class 0 OID 0)
-- Dependencies: 229
-- Name: modules_module_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.modules_module_id_seq OWNED BY public.modules.module_id;


--
-- TOC entry 263 (class 1259 OID 49181)
-- Name: opportunity_owners; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.opportunity_owners (
    opportunity_owner_id integer NOT NULL,
    opportunity_owner_name character varying(255) NOT NULL
);


ALTER TABLE public.opportunity_owners OWNER TO postgres;

--
-- TOC entry 262 (class 1259 OID 49180)
-- Name: opportunity_owners_opportunity_owner_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.opportunity_owners_opportunity_owner_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.opportunity_owners_opportunity_owner_id_seq OWNER TO postgres;

--
-- TOC entry 5364 (class 0 OID 0)
-- Dependencies: 262
-- Name: opportunity_owners_opportunity_owner_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.opportunity_owners_opportunity_owner_id_seq OWNED BY public.opportunity_owners.opportunity_owner_id;


--
-- TOC entry 265 (class 1259 OID 49195)
-- Name: products; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.products (
    product_id integer NOT NULL,
    product_name character varying(255) NOT NULL,
    business_line_id integer NOT NULL,
    is_active boolean DEFAULT true
);


ALTER TABLE public.products OWNER TO postgres;

--
-- TOC entry 264 (class 1259 OID 49194)
-- Name: products_product_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.products_product_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_product_id_seq OWNER TO postgres;

--
-- TOC entry 5365 (class 0 OID 0)
-- Dependencies: 264
-- Name: products_product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.products_product_id_seq OWNED BY public.products.product_id;


--
-- TOC entry 241 (class 1259 OID 42024)
-- Name: projects; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.projects (
    project_id integer NOT NULL,
    project_name character varying(255) NOT NULL,
    cost_center_no character varying(100),
    po_number character varying(100),
    customer_id integer NOT NULL,
    contract_date date,
    expected_end_date date,
    actual_end_date date,
    business_line_id integer NOT NULL
);


ALTER TABLE public.projects OWNER TO postgres;

--
-- TOC entry 240 (class 1259 OID 42023)
-- Name: projects_project_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.projects_project_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.projects_project_id_seq OWNER TO postgres;

--
-- TOC entry 5366 (class 0 OID 0)
-- Dependencies: 240
-- Name: projects_project_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.projects_project_id_seq OWNED BY public.projects.project_id;


--
-- TOC entry 249 (class 1259 OID 42098)
-- Name: revenue_targets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.revenue_targets (
    revenue_target_id integer NOT NULL,
    business_line_id integer NOT NULL,
    target_year integer NOT NULL,
    target_month integer,
    target_revenue_amount numeric,
    target_revenue_currency character varying(10),
    target_revenue_exchange_rate numeric,
    CONSTRAINT revenue_targets_target_month_check CHECK (((target_month IS NULL) OR ((target_month >= 1) AND (target_month <= 12))))
);


ALTER TABLE public.revenue_targets OWNER TO postgres;

--
-- TOC entry 248 (class 1259 OID 42097)
-- Name: revenue_targets_revenue_target_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.revenue_targets_revenue_target_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.revenue_targets_revenue_target_id_seq OWNER TO postgres;

--
-- TOC entry 5367 (class 0 OID 0)
-- Dependencies: 248
-- Name: revenue_targets_revenue_target_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.revenue_targets_revenue_target_id_seq OWNED BY public.revenue_targets.revenue_target_id;


--
-- TOC entry 233 (class 1259 OID 41934)
-- Name: role_module_permissions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.role_module_permissions (
    permission_id integer NOT NULL,
    role_id integer NOT NULL,
    module_id integer NOT NULL,
    can_create boolean DEFAULT false,
    can_read boolean DEFAULT false,
    can_update boolean DEFAULT false,
    can_delete boolean DEFAULT false
);


ALTER TABLE public.role_module_permissions OWNER TO postgres;

--
-- TOC entry 232 (class 1259 OID 41933)
-- Name: role_module_permissions_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.role_module_permissions_permission_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.role_module_permissions_permission_id_seq OWNER TO postgres;

--
-- TOC entry 5368 (class 0 OID 0)
-- Dependencies: 232
-- Name: role_module_permissions_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.role_module_permissions_permission_id_seq OWNED BY public.role_module_permissions.permission_id;


--
-- TOC entry 228 (class 1259 OID 41891)
-- Name: roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.roles (
    role_id integer NOT NULL,
    role_name character varying(100) NOT NULL,
    description text,
    is_active boolean DEFAULT true
);


ALTER TABLE public.roles OWNER TO postgres;

--
-- TOC entry 227 (class 1259 OID 41890)
-- Name: roles_role_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.roles_role_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.roles_role_id_seq OWNER TO postgres;

--
-- TOC entry 5369 (class 0 OID 0)
-- Dependencies: 227
-- Name: roles_role_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.roles_role_id_seq OWNED BY public.roles.role_id;


--
-- TOC entry 253 (class 1259 OID 42138)
-- Name: sales; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sales (
    sales_id integer NOT NULL,
    business_line_id integer NOT NULL,
    opportunity_name character varying(255),
    project_id integer,
    total_value numeric,
    closed_date date,
    category character varying(255)
);


ALTER TABLE public.sales OWNER TO postgres;

--
-- TOC entry 252 (class 1259 OID 42137)
-- Name: sales_sales_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sales_sales_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sales_sales_id_seq OWNER TO postgres;

--
-- TOC entry 5370 (class 0 OID 0)
-- Dependencies: 252
-- Name: sales_sales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sales_sales_id_seq OWNED BY public.sales.sales_id;


--
-- TOC entry 247 (class 1259 OID 42078)
-- Name: sales_targets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sales_targets (
    sales_target_id integer NOT NULL,
    business_line_id integer NOT NULL,
    target_year integer NOT NULL,
    target_month integer,
    target_sales_amount numeric,
    target_sales_currency character varying(10),
    target_sales_exchange_rate numeric,
    CONSTRAINT sales_targets_target_month_check CHECK (((target_month IS NULL) OR ((target_month >= 1) AND (target_month <= 12))))
);


ALTER TABLE public.sales_targets OWNER TO postgres;

--
-- TOC entry 246 (class 1259 OID 42077)
-- Name: sales_targets_sales_target_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sales_targets_sales_target_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sales_targets_sales_target_id_seq OWNER TO postgres;

--
-- TOC entry 5371 (class 0 OID 0)
-- Dependencies: 246
-- Name: sales_targets_sales_target_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sales_targets_sales_target_id_seq OWNED BY public.sales_targets.sales_target_id;


--
-- TOC entry 237 (class 1259 OID 41970)
-- Name: sleeping_opportunities; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sleeping_opportunities (
    sleeping_opportunity_id integer NOT NULL,
    lead_id integer,
    business_line_id integer NOT NULL,
    customer_id integer NOT NULL,
    opportunity_name character varying(255),
    stage character varying(100),
    total_value numeric,
    currency character varying(10),
    exchange_rate numeric,
    created_date date,
    reason text,
    opportunity_owner_id integer,
    note text
);


ALTER TABLE public.sleeping_opportunities OWNER TO postgres;

--
-- TOC entry 236 (class 1259 OID 41969)
-- Name: sleeping_opportunities_sleeping_opportunity_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sleeping_opportunities_sleeping_opportunity_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sleeping_opportunities_sleeping_opportunity_id_seq OWNER TO postgres;

--
-- TOC entry 5372 (class 0 OID 0)
-- Dependencies: 236
-- Name: sleeping_opportunities_sleeping_opportunity_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sleeping_opportunities_sleeping_opportunity_id_seq OWNED BY public.sleeping_opportunities.sleeping_opportunity_id;


--
-- TOC entry 245 (class 1259 OID 42060)
-- Name: sub_batch_details; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.sub_batch_details (
    sub_batch_detail_id integer NOT NULL,
    batch_id integer NOT NULL,
    sub_batch_name character varying(255) NOT NULL,
    description text
);


ALTER TABLE public.sub_batch_details OWNER TO postgres;

--
-- TOC entry 244 (class 1259 OID 42059)
-- Name: sub_batch_details_sub_batch_detail_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.sub_batch_details_sub_batch_detail_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sub_batch_details_sub_batch_detail_id_seq OWNER TO postgres;

--
-- TOC entry 5373 (class 0 OID 0)
-- Dependencies: 244
-- Name: sub_batch_details_sub_batch_detail_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.sub_batch_details_sub_batch_detail_id_seq OWNED BY public.sub_batch_details.sub_batch_detail_id;


--
-- TOC entry 224 (class 1259 OID 41862)
-- Name: suppliers; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.suppliers (
    supplier_id integer NOT NULL,
    supplier_name character varying(255) NOT NULL,
    supplier_code character varying(100),
    tax_id character varying(100),
    contact_person character varying(255),
    phone character varying(50),
    email character varying(255),
    address text,
    payment_terms character varying(255),
    default_currency character varying(10),
    is_active boolean DEFAULT true
);


ALTER TABLE public.suppliers OWNER TO postgres;

--
-- TOC entry 223 (class 1259 OID 41861)
-- Name: suppliers_supplier_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.suppliers_supplier_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.suppliers_supplier_id_seq OWNER TO postgres;

--
-- TOC entry 5374 (class 0 OID 0)
-- Dependencies: 223
-- Name: suppliers_supplier_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.suppliers_supplier_id_seq OWNED BY public.suppliers.supplier_id;


--
-- TOC entry 231 (class 1259 OID 41916)
-- Name: user_roles; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_roles (
    user_id integer NOT NULL,
    role_id integer NOT NULL
);


ALTER TABLE public.user_roles OWNER TO postgres;

--
-- TOC entry 226 (class 1259 OID 41874)
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    full_name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password_hash character varying(255) NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.users OWNER TO postgres;

--
-- TOC entry 225 (class 1259 OID 41873)
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_user_id_seq OWNER TO postgres;

--
-- TOC entry 5375 (class 0 OID 0)
-- Dependencies: 225
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- TOC entry 4953 (class 2604 OID 42048)
-- Name: batches batch_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.batches ALTER COLUMN batch_id SET DEFAULT nextval('public.batches_batch_id_seq'::regclass);


--
-- TOC entry 4963 (class 2604 OID 42258)
-- Name: budget_update_requests request_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests ALTER COLUMN request_id SET DEFAULT nextval('public.budget_update_requests_request_id_seq'::regclass);


--
-- TOC entry 4962 (class 2604 OID 42232)
-- Name: budgets budget_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets ALTER COLUMN budget_id SET DEFAULT nextval('public.budgets_budget_id_seq'::regclass);


--
-- TOC entry 4933 (class 2604 OID 41843)
-- Name: business_lines business_line_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.business_lines ALTER COLUMN business_line_id SET DEFAULT nextval('public.business_lines_business_line_id_seq'::regclass);


--
-- TOC entry 4961 (class 2604 OID 49234)
-- Name: cash_in cash_in_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_in ALTER COLUMN cash_in_id SET DEFAULT nextval('public.cash_in_cash_in_id_seq'::regclass);


--
-- TOC entry 4960 (class 2604 OID 49264)
-- Name: cash_out cash_out_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_out ALTER COLUMN cash_out_id SET DEFAULT nextval('public.cash_out_cash_out_id_seq'::regclass);


--
-- TOC entry 4957 (class 2604 OID 42121)
-- Name: collection_targets collection_target_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.collection_targets ALTER COLUMN collection_target_id SET DEFAULT nextval('public.collection_targets_collection_target_id_seq'::regclass);


--
-- TOC entry 4935 (class 2604 OID 41856)
-- Name: customers customer_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.customers ALTER COLUMN customer_id SET DEFAULT nextval('public.customers_customer_id_seq'::regclass);


--
-- TOC entry 4967 (class 2604 OID 49219)
-- Name: hot_deals hot_deal_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hot_deals ALTER COLUMN hot_deal_id SET DEFAULT nextval('public.hot_deals_hot_deal_id_seq'::regclass);


--
-- TOC entry 4959 (class 2604 OID 42162)
-- Name: invoices invoice_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices ALTER COLUMN invoice_id SET DEFAULT nextval('public.invoices_invoice_id_seq'::regclass);


--
-- TOC entry 4949 (class 2604 OID 41963)
-- Name: leads_tracking id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.leads_tracking ALTER COLUMN id SET DEFAULT nextval('public.leads_tracking_id_seq'::regclass);


--
-- TOC entry 4951 (class 2604 OID 42000)
-- Name: lost_deals lost_deal_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals ALTER COLUMN lost_deal_id SET DEFAULT nextval('public.lost_deals_lost_deal_id_seq'::regclass);


--
-- TOC entry 4943 (class 2604 OID 41908)
-- Name: modules module_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modules ALTER COLUMN module_id SET DEFAULT nextval('public.modules_module_id_seq'::regclass);


--
-- TOC entry 4964 (class 2604 OID 49184)
-- Name: opportunity_owners opportunity_owner_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.opportunity_owners ALTER COLUMN opportunity_owner_id SET DEFAULT nextval('public.opportunity_owners_opportunity_owner_id_seq'::regclass);


--
-- TOC entry 4965 (class 2604 OID 49198)
-- Name: products product_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products ALTER COLUMN product_id SET DEFAULT nextval('public.products_product_id_seq'::regclass);


--
-- TOC entry 4952 (class 2604 OID 42027)
-- Name: projects project_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects ALTER COLUMN project_id SET DEFAULT nextval('public.projects_project_id_seq'::regclass);


--
-- TOC entry 4956 (class 2604 OID 42101)
-- Name: revenue_targets revenue_target_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.revenue_targets ALTER COLUMN revenue_target_id SET DEFAULT nextval('public.revenue_targets_revenue_target_id_seq'::regclass);


--
-- TOC entry 4944 (class 2604 OID 41937)
-- Name: role_module_permissions permission_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_module_permissions ALTER COLUMN permission_id SET DEFAULT nextval('public.role_module_permissions_permission_id_seq'::regclass);


--
-- TOC entry 4941 (class 2604 OID 41894)
-- Name: roles role_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles ALTER COLUMN role_id SET DEFAULT nextval('public.roles_role_id_seq'::regclass);


--
-- TOC entry 4958 (class 2604 OID 42141)
-- Name: sales sales_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales ALTER COLUMN sales_id SET DEFAULT nextval('public.sales_sales_id_seq'::regclass);


--
-- TOC entry 4955 (class 2604 OID 42081)
-- Name: sales_targets sales_target_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales_targets ALTER COLUMN sales_target_id SET DEFAULT nextval('public.sales_targets_sales_target_id_seq'::regclass);


--
-- TOC entry 4950 (class 2604 OID 41973)
-- Name: sleeping_opportunities sleeping_opportunity_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities ALTER COLUMN sleeping_opportunity_id SET DEFAULT nextval('public.sleeping_opportunities_sleeping_opportunity_id_seq'::regclass);


--
-- TOC entry 4954 (class 2604 OID 42063)
-- Name: sub_batch_details sub_batch_detail_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sub_batch_details ALTER COLUMN sub_batch_detail_id SET DEFAULT nextval('public.sub_batch_details_sub_batch_detail_id_seq'::regclass);


--
-- TOC entry 4936 (class 2604 OID 41865)
-- Name: suppliers supplier_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.suppliers ALTER COLUMN supplier_id SET DEFAULT nextval('public.suppliers_supplier_id_seq'::regclass);


--
-- TOC entry 4938 (class 2604 OID 41877)
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- TOC entry 5319 (class 0 OID 42045)
-- Dependencies: 243
-- Data for Name: batches; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.batches (batch_id, project_id, batch_name) FROM stdin;
\.


--
-- TOC entry 5337 (class 0 OID 42255)
-- Dependencies: 261
-- Data for Name: budget_update_requests; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.budget_update_requests (request_id, budget_id, project_id, sub_batch_detail_id, supplier_id, request_code, request_type, status, reason, requested_by_user_id, requested_date, approved_by_user_id, approved_date, implemented_date) FROM stdin;
\.


--
-- TOC entry 5335 (class 0 OID 42229)
-- Dependencies: 259
-- Data for Name: budgets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.budgets (budget_id, project_id, sub_batch_detail_id, cost_type, revenue_amount, revenue_currency, revenue_exchange_rate, freight_amount, freight_currency, freight_exchange_rate, supplier_cost_amount, supplier_cost_currency, supplier_cost_exchange_rate, supplier_id) FROM stdin;
\.


--
-- TOC entry 5296 (class 0 OID 41840)
-- Dependencies: 220
-- Data for Name: business_lines; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.business_lines (business_line_id, business_line_name, bl_code, is_active) FROM stdin;
\.


--
-- TOC entry 5333 (class 0 OID 42202)
-- Dependencies: 257
-- Data for Name: cash_in; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_in (cash_in_id, business_line_id, project_id, description, invoice_id, status, transaction_category, amount_egp, amount_usd, rate, total_value, planned_date, actual_date) FROM stdin;
\.


--
-- TOC entry 5332 (class 0 OID 42175)
-- Dependencies: 256
-- Data for Name: cash_out; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.cash_out (cash_out_id, business_line_id, project_id, description, supplier_invoice, po_number, transaction_category, amount_egp, amount_usd, usd_exchange_rate, amount_eur, eur_exchange_rate, total_value, planned_date, requested_date, paid_date, status) FROM stdin;
\.


--
-- TOC entry 5327 (class 0 OID 42118)
-- Dependencies: 251
-- Data for Name: collection_targets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.collection_targets (collection_target_id, business_line_id, target_year, target_month, target_amount, currency, exchange_rate) FROM stdin;
\.


--
-- TOC entry 5298 (class 0 OID 41853)
-- Dependencies: 222
-- Data for Name: customers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.customers (customer_id, customer_name) FROM stdin;
\.


--
-- TOC entry 5343 (class 0 OID 49216)
-- Dependencies: 267
-- Data for Name: hot_deals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.hot_deals (hot_deal_id, business_line_id, customer_name, qty, description, value, exchange_rate, note, deal_date) FROM stdin;
\.


--
-- TOC entry 5331 (class 0 OID 42159)
-- Dependencies: 255
-- Data for Name: invoices; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.invoices (invoice_id, project_id, description, invoice_number, invoice_date, total_amount, vat_amount, amount_with_vat, status, collected_date, invoice_due_date, category, business_line_id, customer_id, vat_number, currency, collection_rate) FROM stdin;
\.


--
-- TOC entry 5311 (class 0 OID 41960)
-- Dependencies: 235
-- Data for Name: leads_tracking; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.leads_tracking (id, platform, contact_email, mobile_number, inquiries, business_unit, owner, status, lead_date, response_date, response_time, note) FROM stdin;
\.


--
-- TOC entry 5315 (class 0 OID 41997)
-- Dependencies: 239
-- Data for Name: lost_deals; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.lost_deals (lost_deal_id, lead_id, business_line_id, customer_id, opportunity_name, stage, total_value, currency, exchange_rate, created_date, closed_date, loss_reason, account_name, opportunity_owner_id, reason, note, sector) FROM stdin;
\.


--
-- TOC entry 5306 (class 0 OID 41905)
-- Dependencies: 230
-- Data for Name: modules; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.modules (module_id, module_code, module_name, img) FROM stdin;
1	ROLE	Role Management	\N
\.


--
-- TOC entry 5339 (class 0 OID 49181)
-- Dependencies: 263
-- Data for Name: opportunity_owners; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.opportunity_owners (opportunity_owner_id, opportunity_owner_name) FROM stdin;
\.


--
-- TOC entry 5341 (class 0 OID 49195)
-- Dependencies: 265
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.products (product_id, product_name, business_line_id, is_active) FROM stdin;
\.


--
-- TOC entry 5317 (class 0 OID 42024)
-- Dependencies: 241
-- Data for Name: projects; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.projects (project_id, project_name, cost_center_no, po_number, customer_id, contract_date, expected_end_date, actual_end_date, business_line_id) FROM stdin;
\.


--
-- TOC entry 5325 (class 0 OID 42098)
-- Dependencies: 249
-- Data for Name: revenue_targets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.revenue_targets (revenue_target_id, business_line_id, target_year, target_month, target_revenue_amount, target_revenue_currency, target_revenue_exchange_rate) FROM stdin;
\.


--
-- TOC entry 5309 (class 0 OID 41934)
-- Dependencies: 233
-- Data for Name: role_module_permissions; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.role_module_permissions (permission_id, role_id, module_id, can_create, can_read, can_update, can_delete) FROM stdin;
3	2	1	f	t	f	f
5	1	1	t	t	t	t
\.


--
-- TOC entry 5304 (class 0 OID 41891)
-- Dependencies: 228
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.roles (role_id, role_name, description, is_active) FROM stdin;
1	admin	System Administrator	t
2	test		f
\.


--
-- TOC entry 5329 (class 0 OID 42138)
-- Dependencies: 253
-- Data for Name: sales; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sales (sales_id, business_line_id, opportunity_name, project_id, total_value, closed_date, category) FROM stdin;
\.


--
-- TOC entry 5323 (class 0 OID 42078)
-- Dependencies: 247
-- Data for Name: sales_targets; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sales_targets (sales_target_id, business_line_id, target_year, target_month, target_sales_amount, target_sales_currency, target_sales_exchange_rate) FROM stdin;
\.


--
-- TOC entry 5313 (class 0 OID 41970)
-- Dependencies: 237
-- Data for Name: sleeping_opportunities; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sleeping_opportunities (sleeping_opportunity_id, lead_id, business_line_id, customer_id, opportunity_name, stage, total_value, currency, exchange_rate, created_date, reason, opportunity_owner_id, note) FROM stdin;
\.


--
-- TOC entry 5321 (class 0 OID 42060)
-- Dependencies: 245
-- Data for Name: sub_batch_details; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.sub_batch_details (sub_batch_detail_id, batch_id, sub_batch_name, description) FROM stdin;
\.


--
-- TOC entry 5300 (class 0 OID 41862)
-- Dependencies: 224
-- Data for Name: suppliers; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.suppliers (supplier_id, supplier_name, supplier_code, tax_id, contact_person, phone, email, address, payment_terms, default_currency, is_active) FROM stdin;
\.


--
-- TOC entry 5307 (class 0 OID 41916)
-- Dependencies: 231
-- Data for Name: user_roles; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.user_roles (user_id, role_id) FROM stdin;
1	1
\.


--
-- TOC entry 5302 (class 0 OID 41874)
-- Dependencies: 226
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY public.users (user_id, full_name, email, password_hash, is_active, created_at) FROM stdin;
1	Ahmed Amr	ah.amr@elsewedy.com	test	t	2025-12-24 16:35:37.681131
2	ahmed	ahmed@elsewedy.com	admin123	t	2025-12-25 10:21:17.181811
\.


--
-- TOC entry 5376 (class 0 OID 0)
-- Dependencies: 242
-- Name: batches_batch_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.batches_batch_id_seq', 1, false);


--
-- TOC entry 5377 (class 0 OID 0)
-- Dependencies: 260
-- Name: budget_update_requests_request_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.budget_update_requests_request_id_seq', 1, false);


--
-- TOC entry 5378 (class 0 OID 0)
-- Dependencies: 258
-- Name: budgets_budget_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.budgets_budget_id_seq', 1, false);


--
-- TOC entry 5379 (class 0 OID 0)
-- Dependencies: 219
-- Name: business_lines_business_line_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.business_lines_business_line_id_seq', 1, false);


--
-- TOC entry 5380 (class 0 OID 0)
-- Dependencies: 268
-- Name: cash_in_cash_in_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_in_cash_in_id_seq', 1, false);


--
-- TOC entry 5381 (class 0 OID 0)
-- Dependencies: 269
-- Name: cash_out_cash_out_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cash_out_cash_out_id_seq', 1, false);


--
-- TOC entry 5382 (class 0 OID 0)
-- Dependencies: 250
-- Name: collection_targets_collection_target_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.collection_targets_collection_target_id_seq', 1, false);


--
-- TOC entry 5383 (class 0 OID 0)
-- Dependencies: 221
-- Name: customers_customer_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.customers_customer_id_seq', 1, false);


--
-- TOC entry 5384 (class 0 OID 0)
-- Dependencies: 266
-- Name: hot_deals_hot_deal_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.hot_deals_hot_deal_id_seq', 1, false);


--
-- TOC entry 5385 (class 0 OID 0)
-- Dependencies: 254
-- Name: invoices_invoice_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.invoices_invoice_id_seq', 1, false);


--
-- TOC entry 5386 (class 0 OID 0)
-- Dependencies: 234
-- Name: leads_tracking_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.leads_tracking_id_seq', 1, false);


--
-- TOC entry 5387 (class 0 OID 0)
-- Dependencies: 238
-- Name: lost_deals_lost_deal_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.lost_deals_lost_deal_id_seq', 1, false);


--
-- TOC entry 5388 (class 0 OID 0)
-- Dependencies: 229
-- Name: modules_module_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.modules_module_id_seq', 1, true);


--
-- TOC entry 5389 (class 0 OID 0)
-- Dependencies: 262
-- Name: opportunity_owners_opportunity_owner_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.opportunity_owners_opportunity_owner_id_seq', 1, false);


--
-- TOC entry 5390 (class 0 OID 0)
-- Dependencies: 264
-- Name: products_product_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.products_product_id_seq', 1, false);


--
-- TOC entry 5391 (class 0 OID 0)
-- Dependencies: 240
-- Name: projects_project_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.projects_project_id_seq', 1, false);


--
-- TOC entry 5392 (class 0 OID 0)
-- Dependencies: 248
-- Name: revenue_targets_revenue_target_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.revenue_targets_revenue_target_id_seq', 1, false);


--
-- TOC entry 5393 (class 0 OID 0)
-- Dependencies: 232
-- Name: role_module_permissions_permission_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.role_module_permissions_permission_id_seq', 5, true);


--
-- TOC entry 5394 (class 0 OID 0)
-- Dependencies: 227
-- Name: roles_role_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.roles_role_id_seq', 2, true);


--
-- TOC entry 5395 (class 0 OID 0)
-- Dependencies: 252
-- Name: sales_sales_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sales_sales_id_seq', 1, false);


--
-- TOC entry 5396 (class 0 OID 0)
-- Dependencies: 246
-- Name: sales_targets_sales_target_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sales_targets_sales_target_id_seq', 1, false);


--
-- TOC entry 5397 (class 0 OID 0)
-- Dependencies: 236
-- Name: sleeping_opportunities_sleeping_opportunity_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sleeping_opportunities_sleeping_opportunity_id_seq', 1, false);


--
-- TOC entry 5398 (class 0 OID 0)
-- Dependencies: 244
-- Name: sub_batch_details_sub_batch_detail_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.sub_batch_details_sub_batch_detail_id_seq', 1, false);


--
-- TOC entry 5399 (class 0 OID 0)
-- Dependencies: 223
-- Name: suppliers_supplier_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.suppliers_supplier_id_seq', 1, false);


--
-- TOC entry 5400 (class 0 OID 0)
-- Dependencies: 225
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_user_id_seq', 2, true);


--
-- TOC entry 5037 (class 2606 OID 42053)
-- Name: batches batches_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.batches
    ADD CONSTRAINT batches_pkey PRIMARY KEY (batch_id);


--
-- TOC entry 5092 (class 2606 OID 42265)
-- Name: budget_update_requests budget_update_requests_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_pkey PRIMARY KEY (request_id);


--
-- TOC entry 5087 (class 2606 OID 42238)
-- Name: budgets budgets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_pkey PRIMARY KEY (budget_id);


--
-- TOC entry 4977 (class 2606 OID 41851)
-- Name: business_lines business_lines_bl_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.business_lines
    ADD CONSTRAINT business_lines_bl_code_key UNIQUE (bl_code);


--
-- TOC entry 4979 (class 2606 OID 41849)
-- Name: business_lines business_lines_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.business_lines
    ADD CONSTRAINT business_lines_pkey PRIMARY KEY (business_line_id);


--
-- TOC entry 5079 (class 2606 OID 49240)
-- Name: cash_in cash_in_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_in
    ADD CONSTRAINT cash_in_pkey PRIMARY KEY (cash_in_id);


--
-- TOC entry 5071 (class 2606 OID 49270)
-- Name: cash_out cash_out_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_out
    ADD CONSTRAINT cash_out_pkey PRIMARY KEY (cash_out_id);


--
-- TOC entry 5053 (class 2606 OID 42131)
-- Name: collection_targets collection_targets_business_line_id_target_year_target_mont_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.collection_targets
    ADD CONSTRAINT collection_targets_business_line_id_target_year_target_mont_key UNIQUE (business_line_id, target_year, target_month);


--
-- TOC entry 5055 (class 2606 OID 42129)
-- Name: collection_targets collection_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.collection_targets
    ADD CONSTRAINT collection_targets_pkey PRIMARY KEY (collection_target_id);


--
-- TOC entry 4983 (class 2606 OID 41860)
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (customer_id);


--
-- TOC entry 5105 (class 2606 OID 49227)
-- Name: hot_deals hot_deals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hot_deals
    ADD CONSTRAINT hot_deals_pkey PRIMARY KEY (hot_deal_id);


--
-- TOC entry 5069 (class 2606 OID 42168)
-- Name: invoices invoices_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (invoice_id);


--
-- TOC entry 5018 (class 2606 OID 41968)
-- Name: leads_tracking leads_tracking_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.leads_tracking
    ADD CONSTRAINT leads_tracking_pkey PRIMARY KEY (id);


--
-- TOC entry 5030 (class 2606 OID 42007)
-- Name: lost_deals lost_deals_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals
    ADD CONSTRAINT lost_deals_pkey PRIMARY KEY (lost_deal_id);


--
-- TOC entry 5001 (class 2606 OID 41915)
-- Name: modules modules_module_code_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_module_code_key UNIQUE (module_code);


--
-- TOC entry 5003 (class 2606 OID 41913)
-- Name: modules modules_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.modules
    ADD CONSTRAINT modules_pkey PRIMARY KEY (module_id);


--
-- TOC entry 5099 (class 2606 OID 49188)
-- Name: opportunity_owners opportunity_owners_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.opportunity_owners
    ADD CONSTRAINT opportunity_owners_pkey PRIMARY KEY (opportunity_owner_id);


--
-- TOC entry 5103 (class 2606 OID 49204)
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (product_id);


--
-- TOC entry 5035 (class 2606 OID 42033)
-- Name: projects projects_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_pkey PRIMARY KEY (project_id);


--
-- TOC entry 5049 (class 2606 OID 42111)
-- Name: revenue_targets revenue_targets_business_line_id_target_year_target_month_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.revenue_targets
    ADD CONSTRAINT revenue_targets_business_line_id_target_year_target_month_key UNIQUE (business_line_id, target_year, target_month);


--
-- TOC entry 5051 (class 2606 OID 42109)
-- Name: revenue_targets revenue_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.revenue_targets
    ADD CONSTRAINT revenue_targets_pkey PRIMARY KEY (revenue_target_id);


--
-- TOC entry 5012 (class 2606 OID 41946)
-- Name: role_module_permissions role_module_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_module_permissions
    ADD CONSTRAINT role_module_permissions_pkey PRIMARY KEY (permission_id);


--
-- TOC entry 5014 (class 2606 OID 41948)
-- Name: role_module_permissions role_module_permissions_role_id_module_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_module_permissions
    ADD CONSTRAINT role_module_permissions_role_id_module_id_key UNIQUE (role_id, module_id);


--
-- TOC entry 4996 (class 2606 OID 41901)
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (role_id);


--
-- TOC entry 4998 (class 2606 OID 41903)
-- Name: roles roles_role_name_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_name_key UNIQUE (role_name);


--
-- TOC entry 5061 (class 2606 OID 42147)
-- Name: sales sales_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_pkey PRIMARY KEY (sales_id);


--
-- TOC entry 5044 (class 2606 OID 42091)
-- Name: sales_targets sales_targets_business_line_id_target_year_target_month_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales_targets
    ADD CONSTRAINT sales_targets_business_line_id_target_year_target_month_key UNIQUE (business_line_id, target_year, target_month);


--
-- TOC entry 5046 (class 2606 OID 42089)
-- Name: sales_targets sales_targets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales_targets
    ADD CONSTRAINT sales_targets_pkey PRIMARY KEY (sales_target_id);


--
-- TOC entry 5024 (class 2606 OID 41980)
-- Name: sleeping_opportunities sleeping_opportunities_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities
    ADD CONSTRAINT sleeping_opportunities_pkey PRIMARY KEY (sleeping_opportunity_id);


--
-- TOC entry 5041 (class 2606 OID 42070)
-- Name: sub_batch_details sub_batch_details_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sub_batch_details
    ADD CONSTRAINT sub_batch_details_pkey PRIMARY KEY (sub_batch_detail_id);


--
-- TOC entry 4987 (class 2606 OID 41872)
-- Name: suppliers suppliers_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_pkey PRIMARY KEY (supplier_id);


--
-- TOC entry 5008 (class 2606 OID 41922)
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (user_id, role_id);


--
-- TOC entry 4992 (class 2606 OID 41889)
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 4994 (class 2606 OID 41887)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 5038 (class 1259 OID 49318)
-- Name: idx_batches_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_batches_project ON public.batches USING btree (project_id);


--
-- TOC entry 5093 (class 1259 OID 49347)
-- Name: idx_budget_update_requests_budget; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budget_update_requests_budget ON public.budget_update_requests USING btree (budget_id);


--
-- TOC entry 5094 (class 1259 OID 49348)
-- Name: idx_budget_update_requests_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budget_update_requests_project ON public.budget_update_requests USING btree (project_id);


--
-- TOC entry 5095 (class 1259 OID 49351)
-- Name: idx_budget_update_requests_requested_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budget_update_requests_requested_date ON public.budget_update_requests USING btree (requested_date);


--
-- TOC entry 5096 (class 1259 OID 49350)
-- Name: idx_budget_update_requests_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budget_update_requests_status ON public.budget_update_requests USING btree (status);


--
-- TOC entry 5097 (class 1259 OID 49349)
-- Name: idx_budget_update_requests_sub_batch; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budget_update_requests_sub_batch ON public.budget_update_requests USING btree (sub_batch_detail_id);


--
-- TOC entry 5088 (class 1259 OID 49344)
-- Name: idx_budgets_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budgets_project ON public.budgets USING btree (project_id);


--
-- TOC entry 5089 (class 1259 OID 49345)
-- Name: idx_budgets_sub_batch; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budgets_sub_batch ON public.budgets USING btree (sub_batch_detail_id);


--
-- TOC entry 5090 (class 1259 OID 49346)
-- Name: idx_budgets_supplier; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_budgets_supplier ON public.budgets USING btree (supplier_id);


--
-- TOC entry 4980 (class 1259 OID 49289)
-- Name: idx_business_lines_is_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_business_lines_is_active ON public.business_lines USING btree (is_active);


--
-- TOC entry 5080 (class 1259 OID 49336)
-- Name: idx_cash_in_actual_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_actual_date ON public.cash_in USING btree (actual_date);


--
-- TOC entry 5081 (class 1259 OID 49332)
-- Name: idx_cash_in_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_business_line ON public.cash_in USING btree (business_line_id);


--
-- TOC entry 5082 (class 1259 OID 49334)
-- Name: idx_cash_in_invoice; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_invoice ON public.cash_in USING btree (invoice_id);


--
-- TOC entry 5083 (class 1259 OID 49335)
-- Name: idx_cash_in_planned_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_planned_date ON public.cash_in USING btree (planned_date);


--
-- TOC entry 5084 (class 1259 OID 49333)
-- Name: idx_cash_in_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_project ON public.cash_in USING btree (project_id);


--
-- TOC entry 5085 (class 1259 OID 49337)
-- Name: idx_cash_in_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_in_status ON public.cash_in USING btree (status);


--
-- TOC entry 5072 (class 1259 OID 49338)
-- Name: idx_cash_out_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_business_line ON public.cash_out USING btree (business_line_id);


--
-- TOC entry 5073 (class 1259 OID 49342)
-- Name: idx_cash_out_paid_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_paid_date ON public.cash_out USING btree (paid_date);


--
-- TOC entry 5074 (class 1259 OID 49340)
-- Name: idx_cash_out_planned_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_planned_date ON public.cash_out USING btree (planned_date);


--
-- TOC entry 5075 (class 1259 OID 49339)
-- Name: idx_cash_out_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_project ON public.cash_out USING btree (project_id);


--
-- TOC entry 5076 (class 1259 OID 49341)
-- Name: idx_cash_out_requested_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_requested_date ON public.cash_out USING btree (requested_date);


--
-- TOC entry 5077 (class 1259 OID 49343)
-- Name: idx_cash_out_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cash_out_status ON public.cash_out USING btree (status);


--
-- TOC entry 5056 (class 1259 OID 49322)
-- Name: idx_collection_targets_bl_year; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_collection_targets_bl_year ON public.collection_targets USING btree (business_line_id, target_year);


--
-- TOC entry 4984 (class 1259 OID 49290)
-- Name: idx_customers_customer_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_customers_customer_name ON public.customers USING btree (customer_name);


--
-- TOC entry 5106 (class 1259 OID 49311)
-- Name: idx_hot_deals_bl; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_hot_deals_bl ON public.hot_deals USING btree (business_line_id);


--
-- TOC entry 5107 (class 1259 OID 49312)
-- Name: idx_hot_deals_deal_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_hot_deals_deal_date ON public.hot_deals USING btree (deal_date);


--
-- TOC entry 5062 (class 1259 OID 49327)
-- Name: idx_invoices_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_business_line ON public.invoices USING btree (business_line_id);


--
-- TOC entry 5063 (class 1259 OID 49328)
-- Name: idx_invoices_customer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_customer ON public.invoices USING btree (customer_id);


--
-- TOC entry 5064 (class 1259 OID 49330)
-- Name: idx_invoices_due_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_due_date ON public.invoices USING btree (invoice_due_date);


--
-- TOC entry 5065 (class 1259 OID 49329)
-- Name: idx_invoices_invoice_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_invoice_date ON public.invoices USING btree (invoice_date);


--
-- TOC entry 5066 (class 1259 OID 49326)
-- Name: idx_invoices_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_project ON public.invoices USING btree (project_id);


--
-- TOC entry 5067 (class 1259 OID 49331)
-- Name: idx_invoices_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_invoices_status ON public.invoices USING btree (status);


--
-- TOC entry 5015 (class 1259 OID 49302)
-- Name: idx_leads_tracking_lead_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_leads_tracking_lead_date ON public.leads_tracking USING btree (lead_date);


--
-- TOC entry 5016 (class 1259 OID 49301)
-- Name: idx_leads_tracking_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_leads_tracking_status ON public.leads_tracking USING btree (status);


--
-- TOC entry 5025 (class 1259 OID 49307)
-- Name: idx_lost_deals_bl; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_lost_deals_bl ON public.lost_deals USING btree (business_line_id);


--
-- TOC entry 5026 (class 1259 OID 49310)
-- Name: idx_lost_deals_closed_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_lost_deals_closed_date ON public.lost_deals USING btree (closed_date);


--
-- TOC entry 5027 (class 1259 OID 49308)
-- Name: idx_lost_deals_customer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_lost_deals_customer ON public.lost_deals USING btree (customer_id);


--
-- TOC entry 5028 (class 1259 OID 49309)
-- Name: idx_lost_deals_owner; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_lost_deals_owner ON public.lost_deals USING btree (opportunity_owner_id);


--
-- TOC entry 5100 (class 1259 OID 49313)
-- Name: idx_products_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_business_line ON public.products USING btree (business_line_id);


--
-- TOC entry 5101 (class 1259 OID 49314)
-- Name: idx_products_is_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_products_is_active ON public.products USING btree (is_active);


--
-- TOC entry 5031 (class 1259 OID 49315)
-- Name: idx_projects_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_projects_business_line ON public.projects USING btree (business_line_id);


--
-- TOC entry 5032 (class 1259 OID 49317)
-- Name: idx_projects_contract_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_projects_contract_date ON public.projects USING btree (contract_date);


--
-- TOC entry 5033 (class 1259 OID 49316)
-- Name: idx_projects_customer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_projects_customer ON public.projects USING btree (customer_id);


--
-- TOC entry 5047 (class 1259 OID 49321)
-- Name: idx_revenue_targets_bl_year; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_revenue_targets_bl_year ON public.revenue_targets USING btree (business_line_id, target_year);


--
-- TOC entry 5009 (class 1259 OID 49300)
-- Name: idx_role_module_permissions_module_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_role_module_permissions_module_id ON public.role_module_permissions USING btree (module_id);


--
-- TOC entry 5010 (class 1259 OID 49299)
-- Name: idx_role_module_permissions_role_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_role_module_permissions_role_id ON public.role_module_permissions USING btree (role_id);


--
-- TOC entry 5057 (class 1259 OID 49323)
-- Name: idx_sales_business_line; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_business_line ON public.sales USING btree (business_line_id);


--
-- TOC entry 5058 (class 1259 OID 49325)
-- Name: idx_sales_closed_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_closed_date ON public.sales USING btree (closed_date);


--
-- TOC entry 5059 (class 1259 OID 49324)
-- Name: idx_sales_project; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_project ON public.sales USING btree (project_id);


--
-- TOC entry 5042 (class 1259 OID 49320)
-- Name: idx_sales_targets_bl_year; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sales_targets_bl_year ON public.sales_targets USING btree (business_line_id, target_year);


--
-- TOC entry 5019 (class 1259 OID 49303)
-- Name: idx_sleeping_opportunities_bl; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sleeping_opportunities_bl ON public.sleeping_opportunities USING btree (business_line_id);


--
-- TOC entry 5020 (class 1259 OID 49306)
-- Name: idx_sleeping_opportunities_created_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sleeping_opportunities_created_date ON public.sleeping_opportunities USING btree (created_date);


--
-- TOC entry 5021 (class 1259 OID 49304)
-- Name: idx_sleeping_opportunities_customer; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sleeping_opportunities_customer ON public.sleeping_opportunities USING btree (customer_id);


--
-- TOC entry 5022 (class 1259 OID 49305)
-- Name: idx_sleeping_opportunities_owner; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sleeping_opportunities_owner ON public.sleeping_opportunities USING btree (opportunity_owner_id);


--
-- TOC entry 5039 (class 1259 OID 49319)
-- Name: idx_sub_batches_batch; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_sub_batches_batch ON public.sub_batch_details USING btree (batch_id);


--
-- TOC entry 4985 (class 1259 OID 49292)
-- Name: idx_suppliers_is_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_suppliers_is_active ON public.suppliers USING btree (is_active);


--
-- TOC entry 5005 (class 1259 OID 49298)
-- Name: idx_user_roles_role_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_roles_role_id ON public.user_roles USING btree (role_id);


--
-- TOC entry 5006 (class 1259 OID 49297)
-- Name: idx_user_roles_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_roles_user_id ON public.user_roles USING btree (user_id);


--
-- TOC entry 4989 (class 1259 OID 49294)
-- Name: idx_users_is_active; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_is_active ON public.users USING btree (is_active);


--
-- TOC entry 4981 (class 1259 OID 49288)
-- Name: uq_business_lines_bl_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_business_lines_bl_code ON public.business_lines USING btree (bl_code);


--
-- TOC entry 5004 (class 1259 OID 49296)
-- Name: uq_modules_module_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_modules_module_code ON public.modules USING btree (module_code);


--
-- TOC entry 4999 (class 1259 OID 49295)
-- Name: uq_roles_role_name; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_roles_role_name ON public.roles USING btree (role_name);


--
-- TOC entry 4988 (class 1259 OID 49291)
-- Name: uq_suppliers_supplier_code; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_suppliers_supplier_code ON public.suppliers USING btree (supplier_code);


--
-- TOC entry 4990 (class 1259 OID 49293)
-- Name: uq_users_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX uq_users_email ON public.users USING btree (email);


--
-- TOC entry 5122 (class 2606 OID 42054)
-- Name: batches batches_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.batches
    ADD CONSTRAINT batches_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id) ON DELETE CASCADE;


--
-- TOC entry 5140 (class 2606 OID 42291)
-- Name: budget_update_requests budget_update_requests_approved_by_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_approved_by_user_id_fkey FOREIGN KEY (approved_by_user_id) REFERENCES public.users(user_id);


--
-- TOC entry 5141 (class 2606 OID 42266)
-- Name: budget_update_requests budget_update_requests_budget_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_budget_id_fkey FOREIGN KEY (budget_id) REFERENCES public.budgets(budget_id);


--
-- TOC entry 5142 (class 2606 OID 42271)
-- Name: budget_update_requests budget_update_requests_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 5143 (class 2606 OID 42286)
-- Name: budget_update_requests budget_update_requests_requested_by_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_requested_by_user_id_fkey FOREIGN KEY (requested_by_user_id) REFERENCES public.users(user_id);


--
-- TOC entry 5144 (class 2606 OID 42276)
-- Name: budget_update_requests budget_update_requests_sub_batch_detail_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_sub_batch_detail_id_fkey FOREIGN KEY (sub_batch_detail_id) REFERENCES public.sub_batch_details(sub_batch_detail_id);


--
-- TOC entry 5145 (class 2606 OID 42281)
-- Name: budget_update_requests budget_update_requests_supplier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budget_update_requests
    ADD CONSTRAINT budget_update_requests_supplier_id_fkey FOREIGN KEY (supplier_id) REFERENCES public.suppliers(supplier_id);


--
-- TOC entry 5137 (class 2606 OID 42239)
-- Name: budgets budgets_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 5138 (class 2606 OID 42244)
-- Name: budgets budgets_sub_batch_detail_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_sub_batch_detail_id_fkey FOREIGN KEY (sub_batch_detail_id) REFERENCES public.sub_batch_details(sub_batch_detail_id);


--
-- TOC entry 5139 (class 2606 OID 42249)
-- Name: budgets budgets_supplier_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.budgets
    ADD CONSTRAINT budgets_supplier_id_fkey FOREIGN KEY (supplier_id) REFERENCES public.suppliers(supplier_id);


--
-- TOC entry 5126 (class 2606 OID 42132)
-- Name: collection_targets collection_targets_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.collection_targets
    ADD CONSTRAINT collection_targets_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5134 (class 2606 OID 49247)
-- Name: cash_in fk_cash_in_business_line; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_in
    ADD CONSTRAINT fk_cash_in_business_line FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id) ON DELETE RESTRICT;


--
-- TOC entry 5135 (class 2606 OID 49257)
-- Name: cash_in fk_cash_in_invoice; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_in
    ADD CONSTRAINT fk_cash_in_invoice FOREIGN KEY (invoice_id) REFERENCES public.invoices(invoice_id) ON DELETE SET NULL;


--
-- TOC entry 5136 (class 2606 OID 49252)
-- Name: cash_in fk_cash_in_project; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_in
    ADD CONSTRAINT fk_cash_in_project FOREIGN KEY (project_id) REFERENCES public.projects(project_id) ON DELETE RESTRICT;


--
-- TOC entry 5132 (class 2606 OID 49277)
-- Name: cash_out fk_cash_out_business_line; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_out
    ADD CONSTRAINT fk_cash_out_business_line FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id) ON DELETE RESTRICT;


--
-- TOC entry 5133 (class 2606 OID 49282)
-- Name: cash_out fk_cash_out_project; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cash_out
    ADD CONSTRAINT fk_cash_out_project FOREIGN KEY (project_id) REFERENCES public.projects(project_id) ON DELETE RESTRICT;


--
-- TOC entry 5147 (class 2606 OID 49228)
-- Name: hot_deals fk_hot_deals_business_line; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.hot_deals
    ADD CONSTRAINT fk_hot_deals_business_line FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id) ON DELETE RESTRICT;


--
-- TOC entry 5129 (class 2606 OID 49169)
-- Name: invoices fk_invoices_business_line; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT fk_invoices_business_line FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id) ON DELETE RESTRICT;


--
-- TOC entry 5130 (class 2606 OID 49174)
-- Name: invoices fk_invoices_customer; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES public.customers(customer_id) ON DELETE RESTRICT;


--
-- TOC entry 5116 (class 2606 OID 49210)
-- Name: lost_deals fk_lost_deals_opportunity_owner; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals
    ADD CONSTRAINT fk_lost_deals_opportunity_owner FOREIGN KEY (opportunity_owner_id) REFERENCES public.opportunity_owners(opportunity_owner_id) ON DELETE RESTRICT;


--
-- TOC entry 5146 (class 2606 OID 49205)
-- Name: products fk_products_business_line; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT fk_products_business_line FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id) ON DELETE RESTRICT;


--
-- TOC entry 5112 (class 2606 OID 49189)
-- Name: sleeping_opportunities fk_sleeping_opportunity_owner; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities
    ADD CONSTRAINT fk_sleeping_opportunity_owner FOREIGN KEY (opportunity_owner_id) REFERENCES public.opportunity_owners(opportunity_owner_id) ON DELETE RESTRICT;


--
-- TOC entry 5131 (class 2606 OID 42169)
-- Name: invoices invoices_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id) ON DELETE CASCADE;


--
-- TOC entry 5117 (class 2606 OID 42013)
-- Name: lost_deals lost_deals_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals
    ADD CONSTRAINT lost_deals_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5118 (class 2606 OID 42018)
-- Name: lost_deals lost_deals_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals
    ADD CONSTRAINT lost_deals_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(customer_id);


--
-- TOC entry 5119 (class 2606 OID 42008)
-- Name: lost_deals lost_deals_lead_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.lost_deals
    ADD CONSTRAINT lost_deals_lead_id_fkey FOREIGN KEY (lead_id) REFERENCES public.leads_tracking(id);


--
-- TOC entry 5120 (class 2606 OID 42039)
-- Name: projects projects_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5121 (class 2606 OID 42034)
-- Name: projects projects_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.projects
    ADD CONSTRAINT projects_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(customer_id);


--
-- TOC entry 5125 (class 2606 OID 42112)
-- Name: revenue_targets revenue_targets_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.revenue_targets
    ADD CONSTRAINT revenue_targets_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5110 (class 2606 OID 41954)
-- Name: role_module_permissions role_module_permissions_module_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_module_permissions
    ADD CONSTRAINT role_module_permissions_module_id_fkey FOREIGN KEY (module_id) REFERENCES public.modules(module_id) ON DELETE CASCADE;


--
-- TOC entry 5111 (class 2606 OID 41949)
-- Name: role_module_permissions role_module_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.role_module_permissions
    ADD CONSTRAINT role_module_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(role_id) ON DELETE CASCADE;


--
-- TOC entry 5127 (class 2606 OID 42148)
-- Name: sales sales_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5128 (class 2606 OID 42153)
-- Name: sales sales_project_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_project_id_fkey FOREIGN KEY (project_id) REFERENCES public.projects(project_id);


--
-- TOC entry 5124 (class 2606 OID 42092)
-- Name: sales_targets sales_targets_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sales_targets
    ADD CONSTRAINT sales_targets_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5113 (class 2606 OID 41986)
-- Name: sleeping_opportunities sleeping_opportunities_business_line_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities
    ADD CONSTRAINT sleeping_opportunities_business_line_id_fkey FOREIGN KEY (business_line_id) REFERENCES public.business_lines(business_line_id);


--
-- TOC entry 5114 (class 2606 OID 41991)
-- Name: sleeping_opportunities sleeping_opportunities_customer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities
    ADD CONSTRAINT sleeping_opportunities_customer_id_fkey FOREIGN KEY (customer_id) REFERENCES public.customers(customer_id);


--
-- TOC entry 5115 (class 2606 OID 41981)
-- Name: sleeping_opportunities sleeping_opportunities_lead_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sleeping_opportunities
    ADD CONSTRAINT sleeping_opportunities_lead_id_fkey FOREIGN KEY (lead_id) REFERENCES public.leads_tracking(id);


--
-- TOC entry 5123 (class 2606 OID 42071)
-- Name: sub_batch_details sub_batch_details_batch_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.sub_batch_details
    ADD CONSTRAINT sub_batch_details_batch_id_fkey FOREIGN KEY (batch_id) REFERENCES public.batches(batch_id) ON DELETE CASCADE;


--
-- TOC entry 5108 (class 2606 OID 41928)
-- Name: user_roles user_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(role_id) ON DELETE CASCADE;


--
-- TOC entry 5109 (class 2606 OID 41923)
-- Name: user_roles user_roles_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id) ON DELETE CASCADE;


-- Completed on 2025-12-28 10:32:30

--
-- PostgreSQL database dump complete
--

\unrestrict n2fd4H9czg2LPryKlc7YxGM15ZxUnWHLPejOIU4vsb6Pk7qy2ekm2qB6ZKhbkRA

