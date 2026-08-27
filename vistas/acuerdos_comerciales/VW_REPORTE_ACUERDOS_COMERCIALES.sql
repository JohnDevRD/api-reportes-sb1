CREATE VIEW "VW_REPORTE_ACUERDOS_COMERCIALES" AS

-- Descuentos por Pronto Pago
-- Promociones y Concursos
-- Comision de Compras
SELECT 
    T0."DocDate",
    T0."DocNum",
    T0."CardCode",
    T0."CardName",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Dscription") AS "Dscription",
    T0."PeyMethod",
    T0."ObjType",
    'Nota de Crédito (ORPC)' AS "Origen",
    T0."DocTotal",
    CAST(NULL AS DECIMAL(19,6)) AS "PaidSum",
    CAST(NULL AS DECIMAL(19,6)) AS "Saldo Pendiente"
FROM 
    ORPC T0
    INNER JOIN RPC1 T1 ON T0."DocEntry" = T1."DocEntry"
WHERE 
    T1."AcctCode" IN ('99000001', '99000002', '99000003')
GROUP BY 
    T0."DocDate", T0."DocNum", T0."CardCode", T0."CardName", T0."DocTotal", T0."PeyMethod", T0."ObjType"

UNION ALL

-- Comision de Compras
-- Espacio y Cabezales
SELECT 
    T0."DocDate",
    T0."DocNum",
    CAST(NULL AS NVARCHAR(15)) AS "CardCode",
    CAST(NULL AS NVARCHAR(100)) AS "CardName",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Dscription") AS "Dscription",
    CAST(NULL AS NVARCHAR(15)) AS "PeyMethod",
    T0."ObjType",
    'Entrada de Mercancía (OIGN)' AS "Origen",
    T0."DocTotal",
    CAST(NULL AS DECIMAL(19,6)) AS "PaidSum",
    CAST(NULL AS DECIMAL(19,6)) AS "Saldo Pendiente"
FROM 
    OIGN T0
    INNER JOIN IGN1 T1 ON T0."DocEntry" = T1."DocEntry"
WHERE 
    T1."AcctCode" IN ('99000003', '99000004')
GROUP BY 
    T0."DocDate", T0."DocNum", T0."DocTotal", T0."ObjType"

UNION ALL

-- Comision de Compras
SELECT 
    T0."DocDate",
    T0."DocNum",
    T0."CardCode",
    T0."CardName",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Descrip") AS "Dscription",
    CAST(NULL AS NVARCHAR(15)) AS "PeyMethod",
    T1."ObjType",
    'Pago Recibido (ORCT)' AS "Origen",
    T0."DocTotal",
    CAST(NULL AS DECIMAL(19,6)) AS "PaidSum",
    CAST(NULL AS DECIMAL(19,6)) AS "Saldo Pendiente"
FROM 
    ORCT T0
    INNER JOIN RCT4 T1 ON T0."DocEntry" = T1."DocNum"
WHERE 
    T1."AcctCode" = '99000003'
GROUP BY 
    T0."DocDate", T0."DocNum", T0."CardCode", T0."CardName", T0."DocTotal", T1."ObjType"

UNION ALL

-- Facturas de Clientes
SELECT 
    T0."DocDate",
    T0."DocNum",
    T0."CardCode",
    T0."CardName",
    MIN(T1."AcctCode") AS "AcctCode",
    MIN(T1."Dscription") AS "Dscription",
    T0."PeyMethod",
    T0."ObjType",
    'Factura (OINV)' AS "Origen",
    T0."DocTotal",
    T0."PaidSum",
    (T0."DocTotal" - T0."PaidSum") AS "Saldo Pendiente"
FROM 
    OINV T0
    INNER JOIN INV1 T1 ON T0."DocEntry" = T1."DocEntry"
WHERE 
    T1."AcctCode" IN ('99000001', '99000002', '99000003')
GROUP BY 
    T0."DocDate", T0."DocNum", T0."CardCode", T0."CardName", T0."DocTotal", T0."PaidSum", T0."PeyMethod", T0."ObjType"

ORDER BY 
    "DocDate" DESC;