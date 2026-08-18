CREATE VIEW
    "VW_REPORTE_CAJA_CHICA" AS
SELECT
    T0."DocNum" AS "Pago",
    T0."DocDate",
    T0."CardCode",
    T0."CardName",
    T0."CashAcct",
    CASE 
        WHEN T0."DocType" = 'C' THEN 'CLIENTE (A CUENTA)'
        WHEN T0."DocType" = 'S' AND T1."InvType" IS NULL AND T3."DocNum" IS NULL THEN 'PROVEEDOR (A CUENTA)'
        WHEN T1."InvType" = 18 THEN 'FACTURA PROVEEDOR'
        WHEN T1."InvType" = 13 THEN 'FACTURA CLIENTE'
        WHEN T3."DocNum" IS NOT NULL THEN 'CUENTA'
        ELSE 'OTROS'
    END AS "TipoDetalle",
    COALESCE(T1."DocLine", T3."LineId", 0) AS "Linea",
    T1."InvType",
    COALESCE(T2."DocNum", T5."DocNum") AS "DocumentoOrigen",
    COALESCE(T2."DocDate", T5."DocDate") AS "FechaOrigen",
    COALESCE(T2."DocTotal", T5."DocTotal") AS "TotalOrigen",
    -- Prioridad estricta a la cuenta de efectivo
    CASE 
        WHEN T0."CashAcct" = '12345678' THEN 'PRIN'
        WHEN T0."CashAcct" = '87654321' THEN 'VH'
        ELSE T4."OcrCode" 
    END AS "OcrCode",
    COALESCE(T4."GTotal", T6."GTotal", T3."SumApplied", T0."NoDocSum") AS "GTotal",
    
    -- Muestra el Nombre de la Cuenta Mayor (OACT."AcctName") según la línea correspondiente
    COALESCE(A1."AcctName", A2."AcctName", A3."AcctName") AS "Categoria",
    
    COALESCE(T2."NumAtCard", T5."NumAtCard") AS "NumAtCardFactura",
    COALESCE(T2."Comments", T5."Comments") AS "CommentsFactura",
    COALESCE(T2."ObjType", T5."ObjType") AS "ObjType"
FROM OVPM T0
-- Unión con detalle de Facturas (Proveedores o Clientes)
LEFT JOIN VPM2 T1 ON T0."DocEntry" = T1."DocNum"
LEFT JOIN OPCH T2 ON T1."DocEntry" = T2."DocEntry" AND T1."InvType" = 18
LEFT JOIN PCH1 T4 ON T2."DocEntry" = T4."DocEntry"
LEFT JOIN OINV T5 ON T1."DocEntry" = T5."DocEntry" AND T1."InvType" = 13
LEFT JOIN INV1 T6 ON T5."DocEntry" = T6."DocEntry"

-- Unión con detalle de Cuentas en Pagos
LEFT JOIN VPM4 T3 ON T0."DocEntry" = T3."DocNum"

-- Relación con el Plan de Cuentas (OACT) para extraer el nombre de la cuenta
LEFT JOIN OACT A1 ON T4."AcctCode" = A1."AcctCode" -- Desde detalle de Factura de Proveedor
LEFT JOIN OACT A2 ON T6."AcctCode" = A2."AcctCode" -- Desde detalle de Factura de Cliente
LEFT JOIN OACT A3 ON T3."AcctCode" = A3."AcctCode" -- Desde pagos directos a cuenta

WHERE T0."CashAcct" IN ('12345678', '87654321')
ORDER BY "Pago", "Linea";
